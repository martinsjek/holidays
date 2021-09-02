# Holidays

## Setup locally (linux):

### Add local domain
add "holidays.local.io" to /etc/hosts file
> 127.0.0.1 holidays.local.io

### Run docker:
> cd ./docker
> 
> docker-compose up

### Create .env file
Copy existing .env.example file to .env

## Create database inside docker
To create database inside docker container use these commands:

This command will get you inside docker database container:
> docker exec -it db bash

After that login into mysql terminal:
The user is "root" and password is "toor"
> mysql -u root -p

After that your create your database
> create database holidays;

### Update .env file
Replace DATABASE_URL with valid data

Use "db" as the host because that is the name docker database container uses

### Install composer packages and run migrations
Go inside docker webserver container
> docker exec -it webserver bash

Go to the root of project
> cd /var/www/html

Install composer packages
> composer install

Run migrations
> php bin/console doctrine:migrations:migrate

### Open
open url in your browser "holidays.local.io"