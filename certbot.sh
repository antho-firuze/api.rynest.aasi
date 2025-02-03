
# docker run -t --rm --name certbot \
#   -v "/home/nana/api-lsp_exam/certbot/conf/:/etc/letsencrypt" \
#   -v "/home/nana/api-lsp_exam/certbot/logs/:/var/log/letsencrypt" \
#   -v "/home/nana/api-lsp_exam/certbot/data:/var/www/public/letsencrypt" \
#   certbot/certbot certonly -n --force-renewal \
#   --agree-tos --email developerrynest@gmail.com --no-eff-email \
#   --webroot --webroot-path=/var/www/public/letsencrypt \
#   -d api.lsp-ps.id

# docker run -t --rm --name certbot \
#   -v "/home/nana/api-lsp_exam/certbot/conf/:/etc/letsencrypt" \
#   -v "/home/nana/api-lsp_exam/certbot/logs/:/var/log/letsencrypt" \
#   -v "/home/nana/api-lsp_exam/certbot/data:/var/www/public/letsencrypt" \
#   certbot/certbot certonly -n --force-renewal \
#   --agree-tos --email developerrynest@gmail.com --no-eff-email \
#   --webroot --webroot-path=/var/www/public/letsencrypt \
#   -d apito.qonstanta.com
