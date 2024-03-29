FROM ghcr.io/thingsdb/node:latest

# Volume mounts
VOLUME ["/data"]
VOLUME ["/modules"]

# Client (Socket TLS/SSL) connections
EXPOSE 9200
# Status (HTTP) connections
EXPOSE 9001

ENV THINGSDB_BIND_CLIENT_ADDR=0.0.0.0
ENV THINGSDB_BIND_NODE_ADDR=0.0.0.0
ENV THINGSDB_LISTEN_CLIENT_PORT=9200
ENV THINGSDB_HTTP_STATUS_PORT=9001
ENV THINGSDB_MODULES_PATH=/modules
ENV THINGSDB_STORAGE_PATH=/data

ENTRYPOINT ["sh", "-c", "/usr/local/bin/thingsdb --init"]
