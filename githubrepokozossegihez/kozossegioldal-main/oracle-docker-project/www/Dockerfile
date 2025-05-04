FROM php:8.1-apache

# Szükséges csomagok telepítése
RUN apt-get update && apt-get install -y \
    libaio1 \
    unzip \
    wget

# Oracle Instant Client letöltése és telepítése
RUN mkdir -p /opt/oracle && \
    cd /opt/oracle && \
    wget https://download.oracle.com/otn_software/linux/instantclient/2112000/instantclient-basic-linux.x64-21.12.0.0.0dbru.zip && \
    wget https://download.oracle.com/otn_software/linux/instantclient/2112000/instantclient-sdk-linux.x64-21.12.0.0.0dbru.zip && \
    unzip instantclient-basic-linux.x64-21.12.0.0.0dbru.zip && \
    unzip instantclient-sdk-linux.x64-21.12.0.0.0dbru.zip && \
    rm -f instantclient-basic-linux.x64-21.12.0.0.0dbru.zip && \
    rm -f instantclient-sdk-linux.x64-21.12.0.0.0dbru.zip && \
    ln -s /opt/oracle/instantclient_* /opt/oracle/instantclient && \
    echo /opt/oracle/instantclient > /etc/ld.so.conf.d/oracle-instantclient.conf && \
    ldconfig

# OCI8 PHP kiterjesztés telepítése
RUN docker-php-ext-configure oci8 --with-oci8=instantclient,/opt/oracle/instantclient && \
    docker-php-ext-install oci8

WORKDIR /var/www/html