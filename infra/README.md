# Dev Environment

Provisions a full Daybook dev environment on Scaleway with real Kerberos and LDAPS authentication, using Terraform, Cloud-init, and Ansible.

The pipeline:
1. **Terraform** - provisions a DEV1-S VM (2 vCPU, 2 GB RAM, Ubuntu 24.04) with a static IP in `fr-par-1`
2. **Cloud-init** - bootstraps an `ansible` user with your SSH key on first boot
3. **Ansible** - installs Docker CE, activates Swarm, deploys Traefik with manual TLS, then deploys Daybook

## Prerequisites

The following tools must be installed on your workstation:

- [Terraform](https://developer.hashicorp.com/terraform/install) >= 1.0
- [Ansible](https://docs.ansible.com/ansible/latest/installation_guide/) >= 2.14
- A [Scaleway](https://www.scaleway.com) account with an API key

## Step 1 - Generate an Ansible SSH key pair

If you do not already have a dedicated key pair for Ansible:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/id_ansible -C "ansible"
```

The public key (`~/.ssh/id_ansible.pub`) goes into `terraform.tfvars`. The private key stays on your workstation.

## Step 2 - Prepare a TLS certificate

For a dev environment you can use a self-signed certificate:

```bash
openssl req -x509 -newkey rsa:4096 \
  -keyout infra/ansible/secrets/key.pem \
  -out infra/ansible/secrets/cert.pem \
  -days 365 -nodes \
  -subj "/CN=daybook.dev"
```

For a real certificate (e.g. issued by your internal CA), place `cert.pem` and `key.pem` directly in `infra/ansible/secrets/`.

## Step 3 - Prepare secrets

Create the secrets directory and copy the required files:

```bash
mkdir -p infra/ansible/secrets

# Application secrets (from your existing deployment or .env.example)
cp .env            infra/ansible/secrets/.env
cp daybook.keytab  infra/ansible/secrets/daybook.keytab
cp ad-ca.crt       infra/ansible/secrets/ad-ca.crt

# TLS certificate (generated above or provided by your CA)
# infra/ansible/secrets/cert.pem
# infra/ansible/secrets/key.pem
```

`infra/ansible/secrets/` is git-ignored and never committed.

## Step 4 - Configure Terraform

```bash
cp infra/terraform/terraform.tfvars.example infra/terraform/terraform.tfvars
```

Edit `terraform.tfvars` and fill in your Scaleway credentials and the Ansible SSH public key:

```hcl
scw_access_key         = "SCWXXXXXXXXXXXXXXXXX"       # Scaleway access key
scw_secret_key         = "xxxxxxxx-xxxx-xxxx-..."     # Scaleway secret key
scw_project_id         = "xxxxxxxx-xxxx-xxxx-..."     # Scaleway project ID
ansible_ssh_public_key = "ssh-ed25519 AAAA..."        # content of ~/.ssh/id_ansible.pub
```

Your Scaleway credentials are available in the [Scaleway console](https://console.scaleway.com) under **IAM > API keys**.

## Step 5 - Provision the VM

```bash
cd infra/terraform
terraform init
terraform apply
```

Review the plan and confirm. Terraform outputs the server IP when done:

```
Outputs:
  server_ip = "51.158.x.x"
```

## Step 6 - Configure the Ansible inventory

```bash
cp infra/ansible/inventory.ini.example infra/ansible/inventory.ini
```

Edit `inventory.ini` and replace `<SERVER_IP>` with the IP from the previous step:

```ini
[daybook]
51.158.x.x ansible_user=ansible ansible_ssh_private_key_file=~/.ssh/id_ansible
```

## Step 7 - Run the Ansible playbook

Wait ~60 seconds for cloud-init to complete, then:

```bash
cd infra/ansible
ansible-playbook -i inventory.ini playbook.yml
```

The playbook runs four roles in order:

| Role | What it does |
|---|---|
| `docker` | Installs Docker CE from the official apt repository |
| `swarm` | Initialises a single-node Swarm and creates the `traefik-public` overlay network |
| `traefik` | Deploys Traefik v3 with manual TLS under `/appli/traefik/` |
| `daybook` | Clones the repo, copies secrets, and deploys Daybook under `/appli/daybook/` |

## Step 8 - Verify

```bash
# Check Traefik is running
curl -k https://<SERVER_IP>

# Or, if DNS is configured
curl https://daybook.company.com
```

The Daybook login form should appear. Log in with your AD credentials.

## Server directory layout

```
/appli/
  traefik/
    traefik.yml        # Traefik Swarm stack file
    certs/
      cert.pem
      key.pem
    dynamic/
      tls.yml          # Traefik dynamic TLS configuration
  daybook/             # git clone of the repository
    docker-stack.yml
    .env
    daybook.keytab
    ad-ca.crt
    public/
    src/
    docker/
    ...
```

## Tearing down

```bash
cd infra/terraform
terraform destroy
```

This removes the VM, static IP, and security group. The local state file and secrets in `infra/ansible/secrets/` are not touched.

## Updating the deployment

To re-run only a specific role after the initial provisioning:

```bash
ansible-playbook -i inventory.ini playbook.yml --tags daybook
```

Note: roles must have tags defined for this to work. To redeploy Daybook manually on the server:

```bash
ssh ansible@<SERVER_IP>
cd /appli/daybook && git pull
set -a && source .env && set +a
docker stack deploy -c docker-stack.yml daybook
```
