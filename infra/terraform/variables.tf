variable "scw_access_key" {
  description = "Scaleway access key"
  sensitive   = true
}

variable "scw_secret_key" {
  description = "Scaleway secret key"
  sensitive   = true
}

variable "scw_project_id" {
  description = "Scaleway project ID"
}

variable "ansible_ssh_public_key" {
  description = "SSH public key installed for the ansible user on first boot"
}
