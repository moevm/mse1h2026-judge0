#!/bin/bash
# init.sh - Автоматическая настройка Moodle

echo "[Auto-Init] Waiting for Moodle to be fully installed..."
while ! php /opt/bitnami/moodle/admin/cli/cfg.php --name=lang >/dev/null 2>&1; do
    sleep 5
done

echo "[Auto-Init] Moodle is up! Starting auto-configuration..."

php /opt/bitnami/moodle/admin/cli/cfg.php --name=lang --set=ru

echo "[Auto-Init] Installing Russian language pack..."
cat << 'EOF' > /tmp/install_ru.php
<?php
define('CLI_SCRIPT', true);
require('/opt/bitnami/moodle/config.php');
require_once($CFG->libdir.'/componentlib.class.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/course/lib.php');

$lang = 'ru';
$controller = new \tool_langimport\controller();
if ($controller->install_languagepacks([$lang])) {
    echo "Successfully installed language pack: $lang\n";
    global $DB;
    $DB->set_field('user', 'lang', 'ru', array('username' => 'admin'));
} else {
    echo "Failed to install language pack: $lang\n";
}
EOF
php /tmp/install_ru.php
php /opt/bitnami/moodle/admin/cli/purge_caches.php

CR_DIR="/opt/bitnami/moodle/public/question/type/coderunner"
BEH_DIR="/opt/bitnami/moodle/public/question/behaviour/adaptive_adapted_for_coderunner"

download_plugin() {
    URL=$1
    DEST=$2
    NAME=$3
    echo "--> Downloading $NAME..."
    php -r "
        \$ctx = stream_context_create(['http'=>['header'=>'User-Agent: Mozilla/5.0','follow_location'=>1],'ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]);
        \$data = file_get_contents('$URL', false, \$ctx);
        if (\$data) {
            file_put_contents('$DEST', \$data);
            \$zip = new ZipArchive;
            if (\$zip->open('$DEST') === TRUE) {
                \$zip->extractTo('/tmp/');
                \$zip->close();
                echo 'Extracted successfully.' . PHP_EOL;
            }
        } else { echo 'Download failed!' . PHP_EOL; exit(1); }
    "
}

if [ ! -d "$CR_DIR" ] || [ ! -d "$BEH_DIR" ]; then
    echo "[Auto-Init] Starting plugins installation..."
    rm -rf /tmp/moodle-*

    download_plugin "https://github.com/trampgeek/moodle-qbehaviour_adaptive_adapted_for_coderunner/archive/master.zip" "/tmp/beh.zip" "Adaptive Behaviour"
    download_plugin "https://github.com/trampgeek/moodle-qtype_coderunner/archive/master.zip" "/tmp/cr.zip" "CodeRunner"

    echo "[Auto-Init] Copying plugins to Moodle directory..."
    
    SRC_BEH=$(ls -d /tmp/moodle-qbehaviour_adaptive_adapted_for_coderunner-*)
    SRC_CR=$(ls -d /tmp/moodle-qtype_coderunner-*)

    rm -rf "$BEH_DIR" "$CR_DIR"

    if [ -n "$SRC_BEH" ]; then cp -r "$SRC_BEH" "$BEH_DIR"; fi
    if [ -n "$SRC_CR" ]; then cp -r "$SRC_CR" "$CR_DIR"; fi

    # Изменяем права доступа, чтобы браузер (сервер) мог читать JS-файлы Ace Editor
    chown -R daemon:root "$BEH_DIR" "$CR_DIR" 2>/dev/null || true
    find "$BEH_DIR" -type d -exec chmod 755 {} \; 2>/dev/null
    find "$BEH_DIR" -type f -exec chmod 644 {} \; 2>/dev/null
    find "$CR_DIR" -type d -exec chmod 755 {} \; 2>/dev/null
    find "$CR_DIR" -type f -exec chmod 644 {} \; 2>/dev/null

    if [ -d "$CR_DIR" ] && [ -d "$BEH_DIR" ]; then
        echo "[Auto-Init] Plugins installed. Running Moodle upgrade..."
        php /opt/bitnami/moodle/admin/cli/upgrade.php --non-interactive
        
        echo "[Auto-Init] Configuring CodeRunner Sandbox (Jobe)..."
        php /opt/bitnami/moodle/admin/cli/cfg.php --component=qtype_coderunner --name=jobehost --set=jobe
        php /opt/bitnami/moodle/admin/cli/cfg.php --component=qtype_coderunner --name=jobeport --set=80
    else
        echo "[Auto-Init] CRITICAL ERROR: Copy failed. Directories still missing."
        ls -la /opt/bitnami/moodle/public/question/type/
    fi

    rm -f /tmp/beh.zip /tmp/cr.zip
fi

chown -R daemon:root /bitnami/moodledata 2>/dev/null || true

echo "[Auto-Init] Moodle auto-configuration finished!"
