#!/usr/bin/php5
<?php
# Script for updating Dynamic IP on CloudFlare
# M Hughes <hello@msh100.uk>
function api_post ($data) {
    global $api_data;

    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query(array_merge($data, $api_data)),
        ),
    );
    $context = stream_context_create($options);
    $result = json_decode(file_get_contents(API_URL, false, $context), true);

    return($result);
}

function tolog ($text) {
    echo $text . "\n";
}


define('API_URL', 'https://www.cloudflare.com/api_json.html');

$cf_email = getenv('CF_EMAIL');
$cf_api = getenv('CF_API');
$dns_entry = getenv('CF_HOST');

if (!isset($cf_email, $cf_api, $dns_entry)) {
    tolog('CF_EMAIL, CF_API, and CF_HOST must be set');
} else {
    $api_data = array('email' => $cf_email,
                      'tkn' => $cf_api);

    # Determine the DNS host ID
    tolog('Contacting the Cloudflare API to determine DNS zone');

    $entries[] = $last = $dns_entry;
    for ($i = 1; $i <= substr_count($dns_entry, '.'); $i++) {
        $last = substr(strstr($last, '.'), 1);
        $entries[] = $last;
    }

    $zones = api_post(array('a' => 'zone_check',
                            'zones' => implode(',', $entries)));
    
    if (!isset($zones['response'])) {
        tolog ('Bad API credentials');
        die();
    }

    $found = false;
    foreach ($zones['response']['zones'] as $zone_name => $zone) {
        if ($zone > 0) {
            tolog('DNS zone ' . $zone_name . ' found');
            $found = true;
            break;
        }
    }

    if (!$found) {
        tolog('DNS zone for ' . $dns_entry .' could not be found');
    } else {
        # Determine the DNS zone ID
        tolog('Trying to determine DNS record ID');
        $records = api_post(array('a' => 'rec_load_all',
                                  'z' => $zone_name));

        $found = false;
        foreach ($records['response']['recs']['objs'] as $record) {
            if ($record['type'] == 'A' and $record['name'] == $dns_entry) {
                tolog('DNS record ' . $record['name'] . ' found');
                $found = true;
                break;
            }
        }

        if (!$found) {
            tolog ('No DNS A record for ' . $dns_entry . ' found on ' . $zone);
        } else {
            $record_id = $record['rec_id'];

            tolog ('Beginning loop to compare external IP and DNS entry');
            while (true) {
                # Loop DNS resolve and IP compare
                $ip_api = trim(file_get_contents('http://icanhazip.com/'));

                if (filter_var($ip_api, FILTER_VALIDATE_IP)) {
                    if (gethostbyname($dns_entry) !== $ip_api) {
                        tolog ('Updating IP on DNS record');
                        # Updating DNS entry
                        api_post(array('a' => 'rec_edit',
                                       'id' => $record_id,
                                       'z' => $zone_name,
                                       'type' => 'A',
                                       'name' => $dns_entry,
                                       'content' => $ip_api,
                                       'service_mode' => 0,
                                       'ttl' => 120));
                    }
                } else {
                    tolog ('Invalid IP received from API');
                }

                sleep (30);
            }
        }
    }
}
