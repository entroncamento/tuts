#!/bin/bash

# Script de Backup para TUT'S (Oracle Cloud / FAISS & PDFs)
# Este script deve ser agendado no crontab do host.

BACKUP_DIR="/home/opc/backups"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7

mkdir -p $BACKUP_DIR

echo "📦 Iniciando backup dos volumes persistentes..."

# 1. Backup do FAISS
tar -czf $BACKUP_DIR/tuts_faiss_$DATE.tar.gz /var/lib/docker/volumes/tuts-app_tuts-faiss/_data

# 2. Backup dos PDFs
tar -czf $BACKUP_DIR/tuts_pdfs_$DATE.tar.gz /var/lib/docker/volumes/tuts-app_tuts-pdfs/_data

# 3. Backup do PostgreSQL (via Neon API ou manual se fosse local)
# docker exec tuts-db pg_dump -U tuts_user tuts > $BACKUP_DIR/tuts_db_$DATE.sql

# Limpeza de backups antigos
find $BACKUP_DIR -type f -mtime +$RETENTION_DAYS -name "*.tar.gz" -delete

echo "✅ Backup concluído em $BACKUP_DIR"
