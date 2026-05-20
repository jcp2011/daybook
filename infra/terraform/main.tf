terraform {
  required_providers {
    scaleway = {
      source  = "hashicorp/scaleway"
      version = "~> 2.0"
    }
  }
}

provider "scaleway" {
  access_key = var.scw_access_key
  secret_key = var.scw_secret_key
  project_id = var.scw_project_id
  zone       = "fr-par-1"
  region     = "fr-par"
}

resource "scaleway_instance_ip" "daybook" {}

resource "scaleway_instance_security_group" "daybook" {
  name                    = "daybook-dev"
  inbound_default_policy  = "drop"
  outbound_default_policy = "accept"

  inbound_rule {
    action   = "accept"
    port     = 22
    protocol = "TCP"
  }

  inbound_rule {
    action   = "accept"
    port     = 80
    protocol = "TCP"
  }

  inbound_rule {
    action   = "accept"
    port     = 443
    protocol = "TCP"
  }
}

resource "scaleway_instance_server" "daybook" {
  name              = "daybook-dev"
  type              = "DEV1-S"
  image             = "ubuntu_noble"
  ip_id             = scaleway_instance_ip.daybook.id
  security_group_id = scaleway_instance_security_group.daybook.id

  user_data = {
    "cloud-init" = templatefile("${path.module}/cloud-init.yml.tpl", {
      ansible_ssh_public_key = var.ansible_ssh_public_key
    })
  }
}
