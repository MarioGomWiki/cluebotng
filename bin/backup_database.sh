#!/bin/bash
mkdir -p $HOME/mysql_backups
filename=`date +"%d-%m-%Y_%H-%M-%S"`
if [ "$(whoami)" == "tools.cluebot" ];
then
    mysqldump  --defaults-file="${HOME}"/replica.my.cnf -h tools-db s51109__cb > "$HOME/mysql_backups/$filename-cb.sql"
fi
if [ "$(whoami)" == "tools.cluebotng" ];
then
    mysqldump  --defaults-file="${HOME}"/replica.my.cnf -h tools-db s52585__cb > "$HOME/mysql_backups/$filename-cb.sql"
fi