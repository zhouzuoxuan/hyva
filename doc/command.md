sudo systemctl restart php8.3-fpm
sudo systemctl restart elasticsearch


cd app/code/Lencarta/ReactCheckout/reactapp
npm run build


npm install   # 第一次只有需要安装
git config --global --unset url."ssh://git@ssh.github.com:443/".insteadOf

rsync -av --ignore-existing /tmp/lencarta_media_restore/pub/media/ /var/www/lencarta/pub/media/

rsync -aHAX --delete /var/www/httpdocs/pub/media/ /var/www/lencarta/pub/media/
rsync -aHAX --delete /var/www/lencarta-hyva/pub/media/ /var/www/preview/pub/media/

rsync -avz --progress \
  -e "ssh -i Lencarta.pem -p 2222" \
  pub/media/ ubuntu@13.42.55.184:/var/www/lencarta-hyva/pub/media/

wsl --terminate Ubuntu-22.04
wsl --shutdown


rsync -aHAX --delete /var/www/lencarta/shared/pub/media/ pub/media/


