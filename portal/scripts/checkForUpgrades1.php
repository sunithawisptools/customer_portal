<?php
require_once("/usr/share/portal/vendor/autoload.php");
$currentVersion = (string)file_get_contents("/usr/share/portal/resources/version");
echo "Checking for new upgrades newer than $currentVersion!\n";

$client = new GuzzleHttp\Client();
$res = $client->get("https://api.github.com/repos/sonarsoftware/customer_portal/tags");
$body = json_decode($res->getBody()->getContents());
$latestVersion = $body[0]->name;

if (version_compare($currentVersion, $latestVersion) === -1)
{
    echo "There is a newer version, $latestVersion.\n";
    exec("/usr/bin/php /usr/share/portal/artisan down");
    exec("/bin/rm -Rf /tmp/customer_portal");
    exec("/usr/bin/git clone https://github.com/sunithawisptools/customer_portal.git /tmp/customer_portal"); //Clone the repo
    exec("/usr/bin/git -C /tmp/customer_portal checkout test"); //checkout celltex branch
    if (!file_exists("/tmp/customer_portal"))
    {
        echo "Failed to clone customer portal. Try again later.\n";
        return;
    } 
    else
    {
        exec("/bin/cp -R /tmp/customer_portal/portal/* /usr/share/portal/");
        exec("/bin/chown -R www-data:www-data /usr/share/portal");
    }
    echo "Files copied, performing upgrade steps.\n";

    exec("/usr/bin/php /usr/share/portal/artisan up");
    exec("/usr/bin/php /usr/share/portal/artisan migrate --force");
    exec("/usr/bin/php /usr/share/portal/artisan cache:clear");
    exec("/usr/bin/php /usr/share/portal/artisan view:clear");
    exec("/usr/bin/php /usr/share/portal/artisan route:cache");
    exec("/usr/bin/php /usr/share/portal/artisan config:cache");
    exec("/usr/bin/php /usr/share/portal/artisan optimize");

    echo "Portal successfully updated.\n";
    return;
}

echo "You are on the latest version.\n";
