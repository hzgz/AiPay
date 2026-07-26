# 版权归属 TG:RENBUZAIHA 所有
# 唯一发布路径: https://github.com/hzgz/AiPay.git

FROM php:8.3.22-cli-alpine

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories \
  && apk update --no-cache \
  && docker-php-source extract

# install extensions
RUN docker-php-ext-install pdo pdo_mysql -j$(nproc) pcntl

# enable opcache and pcntl
RUN docker-php-ext-enable opcache pcntl
RUN docker-php-source delete \
    rm -rf /var/cache/apk/*

RUN mkdir -p /app
WORKDIR /app