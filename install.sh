#!/bin/bash

#########################################
##        ENVIRONMENTAL CONFIG         ##
#########################################

# Configure user nobody to match unRAID's settings
usermod -u 99 nobody
usermod -g 100 nobody
usermod -d /home nobody
chown -R nobody:users /home

# Disable SSH
rm -rf /etc/service/sshd /etc/my_init.d/00_regen_ssh_host_keys.sh

#########################################
##    REPOSITORIES AND DEPENDENCIES    ##
#########################################

# Repositories
echo 'deb http://archive.ubuntu.com/ubuntu trusty main universe restricted' > /etc/apt/sources.list
echo 'deb http://archive.ubuntu.com/ubuntu trusty-updates main universe restricted' >> /etc/apt/sources.list


# Install Dependencie
apt-get update -qq
apt-get install -qy php5-cli

#########################################
##  FILES, SERVICES AND CONFIGURATION  ##
#########################################

# CONFIG

# Cloudflare
mkdir -p /etc/service/cf
cat <<'EOT' > /etc/service/cf/run
#!/bin/bash
chmod 777 /root/updateip.php
chmod +x /root/updateip.php
/root/updateip.php
EOT

cat <<'EOT' > /etc/my_init.d/00_config.sh
#!/bin/bash
# Fix the timezone
if [[ $(cat /etc/timezone) != $TZ ]] ; then
  echo "$TZ" > /etc/timezone
  dpkg-reconfigure -f noninteractive tzdata
fi
chmod +x /etc/service/cf/run
EOT

chmod -R +x /etc/service/ /etc/my_init.d/

#########################################
##                 CLEANUP             ##
#########################################

# Clean APT install files
apt-get clean -y
rm -rf /var/lib/apt/lists/* /var/cache/* /var/tmp/* 
