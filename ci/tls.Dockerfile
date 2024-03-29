FROM ghcr.io/thingsdb/node:latest
FROM ghcr.io/cesbit/tlsproxy:latest

FROM amd64/alpine:latest
RUN apk update && \
    apk add pcre2 libuv yajl curl tzdata openssl
RUN mkdir -p /var/lib/thingsdb

COPY --from=0 /usr/local/bin/thingsdb /usr/local/bin/
COPY --from=1 /tlsproxy /usr/local/bin/

RUN mkdir /certificates && \
    cd /certificates && \
    openssl genrsa -out server.key 2048 && \
    openssl ecparam -genkey -name secp384r1 -out server.key && \
    openssl req -new -x509 -sha256 -key server.key -out server.crt -days 3650 -nodes -subj "/CN=localhost"

# Volume mounts
VOLUME ["/data"]
VOLUME ["/modules"]
VOLUME ["/certificates"]

# Client (Socket TLS/SSL) connections
EXPOSE 9443
# Status (HTTP) connections
EXPOSE 9002

ENV TLSPROXY_TARGET=127.0.0.1
ENV TLSPROXY_PORTS=9443:9200
ENV TLSPROXY_CERT_FILE=/certificates/server.crt
ENV TLSPROXY_KEY_FILE=/certificates/server.key

ENV THINGSDB_BIND_CLIENT_ADDR=0.0.0.0
ENV THINGSDB_BIND_NODE_ADDR=0.0.0.0
ENV THINGSDB_LISTEN_CLIENT_PORT=9200
ENV THINGSDB_HTTP_STATUS_PORT=9002
ENV THINGSDB_MODULES_PATH=/modules
ENV THINGSDB_STORAGE_PATH=/data

ENTRYPOINT ["sh", "-c", "/usr/local/bin/tlsproxy & /usr/local/bin/thingsdb --init"]
