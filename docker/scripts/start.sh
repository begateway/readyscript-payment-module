#!/bin/bash

# it fixes local license test
/sbin/iptables -t nat -A PREROUTING -p tcp --dport 80 -j REDIRECT --to-ports 8080
/sbin/iptables -t nat -A OUTPUT -p tcp --dport 80 -j REDIRECT --to-port 8080

# Install ReadyScript
if [ ! -e /var/www/html/.first-run-complete ]; then
  rm -f /var/www/html/*
  unzip /readyscript-shop-middle.zip -d /var/www/html
  #rm -f /readyscript-shop-middle.zip

  echo "Do not remove this file." > /var/www/html/.first-run-complete
  echo '<?php
  \Setup::$DETAILED_EXCEPTION = true;' > /var/www/html/_local_settings.php
  
  chown -R nobody.nobody /var/www/html
fi

addgroup nobody tty
chmod o+w /dev/pts/0

su-exec nobody /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
