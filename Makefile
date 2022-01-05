local_config:
	cp .env.local .env
	composer dumpautoload -o -vvv

online_config:
	sed -i 's/^APP_ENV=stage/APP_ENV=online/' .env.online
	sed -i 's/^MAIL_PASSWORD=BullionMonitor2019/MAIL_PASSWORD=BullionMonitor666/' .env.online
	cp .env.online .env
	composer dumpautoload -o -vvv

stage_config:
	sed -i 's/^APP_ENV=online/APP_ENV=stage/' .env.online
	sed -i 's/^MAIL_PASSWORD=BullionMonitor2019/MAIL_PASSWORD=BullionMonitor666/' .env.online
	cp .env.online .env
	composer dumpautoload -o -vvv
