output "server_ip" {
  value       = scaleway_instance_ip.daybook.address
  description = "Public IP - copy into ansible/inventory.ini"
}
