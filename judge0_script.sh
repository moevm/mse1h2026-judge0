#!/bin/bash

multipass launch ~/Downloads/focal-server-cloudimg-amd64.img --name judge0-mini --cpus 1 --memory 2G --disk 25G
echo "Машина judge0-mini запущена"

echo "Настройка GRUB."
multipass exec judge0-mini -- sudo bash -c '
  sed -i "s/^GRUB_CMDLINE_LINUX=\"\(.*\)\"/GRUB_CMDLINE_LINUX=\"\1 systemd.unified_cgroup_hierarchy=0\"/" /etc/default/grub
  update-grub
'
echo "Настройка произведена"

echo "Перезагрузка виртуальной машины для применения настроек ядра."
multipass restart judge0-mini

echo "Установка Docker и развертывание Judge0."
multipass exec judge0-mini -- bash -c '
  sudo apt-get update
  sudo apt-get install -y docker.io docker-compose unzip curl openssl

  sudo usermod -aG docker $USER

  wget https://github.com/judge0/judge0/releases/download/v1.13.1/judge0-v1.13.1.zip
  unzip judge0-v1.13.1.zip
  cd judge0-v1.13.1

  REDIS_PASS=$(openssl rand -hex 16)
  POSTGRES_PASS=$(openssl rand -hex 16)

  sed -i "s/^REDIS_PASSWORD=.*/REDIS_PASSWORD=$REDIS_PASS/" judge0.conf
  sed -i "s/^POSTGRES_PASSWORD=.*/POSTGRES_PASSWORD=$POSTGRES_PASS/" judge0.conf

  echo "Пароли успешно сгенерированы и добавлены в judge0.conf"

 
  sudo docker-compose up -d db redis
  
  echo "Ожидание инициализации БД"
  sleep 10
  
  # Запуск остальных сервисов
  sudo docker-compose up -d
  
  echo "Ожидание финального запуска"
  sleep 5
'

echo "Развертывание завершено."

multipass shell judge0-mini
