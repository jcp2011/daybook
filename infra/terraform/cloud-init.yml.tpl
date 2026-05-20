#cloud-config
users:
  - name: ansible
    groups: [sudo]
    shell: /bin/bash
    sudo: ALL=(ALL) NOPASSWD:ALL
    ssh_authorized_keys:
      - ${ansible_ssh_public_key}

packages:
  - python3
  - python3-apt
  - git

package_update: true
package_upgrade: true
