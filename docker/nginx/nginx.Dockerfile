FROM nginx:alpine

RUN apk add --no-cache gettext

COPY ./docker/nginx/conf.d/default.conf.template /etc/nginx/templates/default.conf.template

# copy nginx configuration files into container nginx config directory

# CMD ["/bin/sh", "-c", "envsubst < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf && exec nginx -g 'daemon off;'"]
CMD ["/bin/sh", "-c", "envsubst '$$SERVER_NAME' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf && exec nginx -g 'daemon off;'"]