<?php

namespace ProcessWire;


class Blackhole extends WireData implements Module, ConfigurableModule
{

  protected $BLACKHOLE_DAT_FILE;

  protected static function getDefaultData()
  {
    return array(
      'ban_logged_users' => 0,
      'redirect_logged_users' => 27, // http404 by default
      'enable_email_alerts' => 0,
      'email_alerts_mailaddress' => '',
      'email_alerts_mailaddress_from' => '',
      'email_alerts_type' => 'default',
      'email_alerts_msg_custom' => '',
      'frontend_warning_msg_type' => 'default',
      'frontend_warning_msg_custom' => '',
      'frontend_banned_msg_type' => 'default',
      'frontend_banned_msg_custom' => '',
      'frontend_show_info' => 1,
      'whitelisted_bots' => 'adsbot-google,aolbuild,baidu,bingbot,bingpreview,duckduckgo,googlebot,msnbot,mediapartners-google,slurp,teoma,yandex',
      'banned_status_code' => 'none',
    );
  }

  public function __construct()
  {
    $this->setArray(self::getDefaultData());
  }

  public function init()
  {

    $this->BLACKHOLE_DAT_FILE = $this->config->paths->siteModules . $this->className . '/blackhole/blackhole.dat';
  }

  protected function blackhole_get_vars()
  {
    $ip = $this->blackhole_get_ip();
    $whois = $this->frontend_show_info ? $this->blackhole_whois() : '';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $this->blackhole_sanitize($_SERVER['HTTP_USER_AGENT']) : null;
    $request = isset($_SERVER['REQUEST_URI']) ? $this->blackhole_sanitize($_SERVER['REQUEST_URI']) : null;
    $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $this->blackhole_sanitize($_SERVER['SERVER_PROTOCOL']) : null;
    $method = isset($_SERVER['REQUEST_METHOD']) ? $this->blackhole_sanitize($_SERVER['REQUEST_METHOD']) : null;
    date_default_timezone_set('UTC');
    $date = date('l, F jS Y @ H:i:s');
    $time = time();

    return array($ip, $whois, $ua, $request, $protocol, $method, $date, $time);
  }

  protected function blackhole_checkbot($ip, $ua, $request)
  {

    $badbot = 0;
    if ($this->ban_logged_users == 1 && $this->user->isLoggedin()) {
      if (!$this->redirect_logged_users) { // NullPage
        return -1;
      } else {
        if ($this->page->id == $this->pages->get('template=blackhole')->id) {
          $this->session->redirect($this->pages->get($this->redirect_logged_users)->url);
        }
      }
      return -1;
    }

    if ($this->blackhole_whitelist($ua)) return -1;

    $filename = $this->BLACKHOLE_DAT_FILE;
    $fp = fopen($filename, 'r') or die('<p>Error: Data File</p>');

    while ($line = fgets($fp)) {
      $ua_logged = explode(' ', $line);
      if ($ua_logged[0] === $ip) {
        $badbot++;
        break;
      }
    }

    fclose($fp);

    $blackholepage = wire('pages')->get('template=blackhole, limit=1');
    if (($badbot === 0) && (strpos($request, $blackholepage->name) === false)) return -1;

    return $badbot;
  }

  public function blackhole()
  {

    list($ip, $whois, $ua, $request, $protocol, $method, $date, $time) = $this->blackhole_get_vars();

    $badbot = $this->blackhole_checkbot($ip, $ua, $request);

    if ($badbot > 0) {
      if ($this->banned_status_code == '403') {
        header('HTTP/1.0 403 Forbidden');
        header("Connection: close");
        exit(0);
      }
      if ($this->frontend_banned_msg_type == 'default') {
        echo '<h1>You have been banned from this domain</h1>';
        echo '<p>If you think there has been a mistake, <a href="/contact/">contact the administrator</a> via proxy server.</p>';
      } else {
        // output custom message
        echo $this->frontend_banned_msg_custom;
      }
      exit(0);
    } elseif ($badbot === 0) {

      $filename = $this->BLACKHOLE_DAT_FILE;

      $fp = fopen($filename, 'a+');

      fwrite($fp, $ip . ' - ' . $method . ' - ' . $protocol . ' - ' . $date . ' - ' . $ua . "\n");

      fclose($fp);

      $message = $date . "\n\n";
      $message .= 'URL Request: ' . $request . "\n";
      $message .= 'IP Address: ' . $ip . "\n";
      $message .= 'User Agent: ' . $ua . "\n\n";
      $message .= 'Whois Lookup: ' . "\n\n" . $whois . "\n";

      if ($this->enable_email_alerts == '1') {
        $mail = wireMail();
        $mail->to($this->email_alerts_mailaddress)->from($this->email_alerts_mailaddress_from);
        $mail->subject("Blackhole banned bot");
        $mail->body(str_replace('\n', '<br>', $message));
        //$mail->bodyHTML($message);
        $result = $mail->send();
      }

      $info = $this->modules->getModuleInfoVerbose('Blackhole');
      if ($this->frontend_warning_msg_type == 'default') {
        $markup = __('<h1>You have fallen into a trap!</h1>
                                <p>
                                    This site&rsquo;s <a href="/robots.txt">robots.txt</a> file explicitly forbids your presence at this location.
                                    The following Whois data will be reviewed carefully. If it is determined that you suck, you will be banned from
                                    this site.
                                    If you think this is a mistake, <em>now</em> is the time to <a href="/contact/">contact the administrator</a>.
                                </p>');
      } else {
        $markup = $this->frontend_warning_msg_custom;
      }
      if ($this->frontend_show_info == '1') {
        $markup .= "<h3>Your IP Address is " . $ip . "</h3><pre>WHOIS Lookup for " . $ip . "\n" . $date . "\n\n" . $whois . "</pre>";
      }
      $markup .= '<p><a href="https://github.com/flydev-fr/Blackhole" title="Blackhole for Bad Bots">Blackhole v' . $info['versionStr'] . '</a></p>';


?>
      <!DOCTYPE html>
      <html lang="en-US">

      <head>
        <title>Welcome to Blackhole!</title>
        <style>
          body {
            color: #fff;
            background-color: #851507;
            font: 14px/1.5 Helvetica, Arial, sans-serif;
          }

          #blackhole {
            margin: 20px auto;
            width: 700px;
          }

          pre {
            padding: 20px;
            white-space: pre-line;
            border-radius: 10px;
            background-color: #b34334;
          }

          a {
            color: #fff;
          }
        </style>
      </head>

      <body>
        <div id="blackhole">
          <?php echo $markup; ?>
        </div>
      </body>

      </html>
<?php

    }

    return false;
  }

  protected function blackhole_whitelist($ua)
  {
    $bots = str_replace(',', '|', $this->whitelisted_bots);
    if (preg_match("/($bots)/i", $ua)) {
      return true;
    }

    return false;
  }

  protected function blackhole_sanitize($string)
  {

    $string = trim($string);
    $string = strip_tags($string);
    $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    $string = str_replace("\n", "", $string);
    $string = trim($string);

    return $string;
  }

  protected function blackhole_get_ip()
  {

    $ip = $this->blackhole_evaluate_ip();

    if (preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $ip, $ip_match)) {

      $ip = $ip_match[1];
    }

    return $this->blackhole_sanitize($ip);
  }

  protected function blackhole_evaluate_ip()
  {

    $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_X_REAL_IP', 'HTTP_X_COMING_FROM', 'HTTP_PROXY_CONNECTION', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'HTTP_COMING_FROM', 'HTTP_VIA', 'REMOTE_ADDR');

    foreach ($ip_keys as $key) {

      if (array_key_exists($key, $_SERVER) === true) {

        foreach (explode(',', $_SERVER[$key]) as $ip) {

          $ip = trim($ip);

          $ip = $this->blackhole_normalize_ip($ip);

          if ($this->blackhole_validate_ip($ip)) {

            return $ip;
          }
        }
      }
    }

    return 'Error: Invalid Address';
  }

  protected function blackhole_normalize_ip($ip)
  {

    if (strpos($ip, ':') !== false && substr_count($ip, '.') == 3 && strpos($ip, '[') === false) {

      // IPv4 with port (e.g., 123.123.123:80)
      $ip = explode(':', $ip);
      $ip = $ip[0];
    } else {

      // IPv6 with port (e.g., [::1]:80)
      $ip = explode(']', $ip);
      $ip = ltrim($ip[0], '[');
    }

    return $ip;
  }

  protected function blackhole_validate_ip($ip)
  {

    $options = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

    $filtered = filter_var($ip, FILTER_VALIDATE_IP, $options);

    if (!$filtered || empty($filtered)) {

      if (preg_match("/^(([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]).){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/", $ip)) {

        return $ip; // IPv4

      } elseif (preg_match("/^\s*((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?\s*$/", $ip)) {

        return $ip; // IPv6

      }

      error_log('Invalid IP Address: ' . $ip);

      return false;
    }

    return $filtered;
  }



  protected function blackhole_whois()
  {
    $msg = '';
    $extra = '';
    $server = 'whois.arin.net';
    $ip = $this->blackhole_get_ip();

    if (!$ip = gethostbyname($ip)) {

      $msg .= 'Can&rsquo;t perform lookup without an IP address.' . "\n\n";
    } else {
      if (!$sock = fsockopen($server, 43, $num, $error, 20)) {

        unset($sock);
        $msg .= 'Timed-out connecting to $server (port 43).' . "\n\n";
      } else {

        // fputs($sock, "$ip\n");
        fputs($sock, "n $ip\n");
        $buffer = '';
        while (!feof($sock)) $buffer .= fgets($sock, 10240);
        fclose($sock);
      }

      if (stripos($buffer, 'ripe.net')) {

        $nextServer = 'whois.ripe.net';
      } elseif (stripos($buffer, 'nic.ad.jp')) {

        $nextServer = 'whois.nic.ad.jp';
        $extra = '/e'; // suppress JaPaNIC characters

      } elseif (stripos($buffer, 'registro.br')) {

        $nextServer = 'whois.registro.br';
      }

      if (isset($nextServer)) {

        $buffer = '';
        $msg .= 'Deferred to specific whois server: ' . $nextServer . '...' . "\n\n";

        if (!$sock = fsockopen($nextServer, 43, $num, $error, 10)) {

          unset($sock);
          $msg .= 'Timed-out connecting to ' . $nextServer . ' (port 43)' . "\n\n";
        } else {

          fputs($sock, $ip . $extra . "\n");
          while (!feof($sock)) $buffer .= fgets($sock, 10240);
          fclose($sock);
        }
      }

      $replacements = array("\n", "\n\n", "");
      $patterns = array("/\\n\\n\\n\\n/i", "/\\n\\n\\n/i", "/#(\s)?/i");
      $buffer = preg_replace($patterns, $replacements, $buffer);
      $buffer = htmlentities(trim($buffer), ENT_QUOTES, 'UTF-8');

      // $msg .= nl2br($buffer);
      $msg .= $buffer;
    }

    return $msg;
  }

  public function ___install()
  {
    $fg = new Fieldgroup();
    $fg->name = 'blackhole';
    $fg->add($this->fields->get('title')); // needed title field
    $fg->save();
    $t = new Template();
    $t->name = 'blackhole';
    $t->fieldgroup = $fg;
    $t->save();
    $t->icon = 'grav';
    $t->save();

    $this->message('In order to get Blackhole working, you have to create a page and assign to this new page the template "blackhole".');
  }

  public function ___uninstall()
  {
    $t = $this->templates->get("blackhole");
    if ($t && $t->getNumPages() > 0) {
      throw new WireException("Can't uninstall because template is used by a page. You must remove the page before uninstalling this module.");
    } elseif ($t) {
      $t = $this->templates->get("blackhole");
      $this->templates->delete($t);
      $this->fieldgroups->delete($t->fieldgroup);
    }
  }

  /**
   * Module configuration
   *
   * @param InputfieldWrapper $inputfields
   *
   */
  public static function getModuleConfigInputfields(array $data)
  {

    $data = array_merge(self::getDefaultData(), $data);

    $wrap = new InputfieldWrapper();


    $blackholepage = wire('pages')->get('template=blackhole, limit=1')->url;
    if (!empty($blackholepage)) {
      $label = __('Robots Rules');
      $description = __('**Add** the following rules to your site\'s **robots.txt** file :');
      $markup = 'User-agent: *<br>Disallow: ' . $blackholepage;
      $note = __('Bots which do not follow the robots.txt rules and are **not** whitelisted will be **banned** by default.');
    } else {
      $label = __('Module Not Ready');
      $description = __('Action Required');
      $markup = __('You must **create a page** and **assign** to this page the template "**blackhole**" then **refresh** this page.');
      $note = __('Blackhole not ready...');
    }
    // markup
    $f = new InputfieldMarkup();
    $f->label = $label;
    $f->description = $description;
    $f->markupText = wire('sanitizer')->entitiesMarkdown($markup, true);
    $f->notes = $note;
    $wrap->add($f);

    // logged users
    $f = new InputfieldCheckbox();
    $f->name = 'ban_logged_users';
    $f->icon = 'users';
    $f->label = __('Logged-in Users');
    $f->label2 = __('Turn-off Blackhole for logged-in users');
    $f->description = __('If a logged user land by « accident » in the black hole, he will be redirected. To ban them, uncheck the box.');
    $f->attr('checked', $data['ban_logged_users'] == '1' ? 'checked' : '');
    $wrap->add($f);

    $f = wire('modules')->get('InputfieldPageListSelect');
    $f->name = 'redirect_logged_users';
    $f->label = 'Redirect Page';
    $f->parent_id = wire('pages')->get('/')->id;
    $f->derefAsPage = FieldtypePage::derefAsPageOrNullPage;
    $f->showIf = "ban_logged_users=1";
    $f->value = $data['redirect_logged_users'];
    $wrap->add($f);

    // email feature
    $fs = new InputfieldFieldset();
    $fs->icon = 'bell';
    $fs->label = __('Email Alerts');
    $fs->description = __('Be aware with Blackhole Email Alerts as it can turn into a **small mail bomb**!');

    $f = new InputfieldCheckbox();
    $f->name = 'enable_email_alerts';
    $f->label = __('Email Alerts');
    $f->label2 = __('Enable email alerts');
    $f->attr('checked', $data['enable_email_alerts'] == '1' ? 'checked' : '');
    $fs->add($f);

    $f = new InputfieldText();
    $f->name = 'email_alerts_mailaddress';
    $f->label = __('Email Address');
    $f->description = __('Email address where alerts will be sent.');
    $f->value = $data['email_alerts_mailaddress'];
    $f->columnWidth = 50;
    $f->showIf = "enable_email_alerts=1";
    $fs->add($f);

    $f = new InputfieldText();
    $f->name = 'email_alerts_mailaddress_from';
    $f->label = __('Email From');
    $f->description = __('Email address for "From" header.');
    $f->value = $data['email_alerts_mailaddress_from'];
    $f->columnWidth = 50;
    $f->showIf = "enable_email_alerts=1";
    $fs->add($f);

    $f = new InputfieldRadios();
    $f->name = 'email_alerts_type';
    $f->label = __('Alert Type');
    $f->description = __('Type of email alert.');
    $f->columnWidth = 50;
    $f->addOptions(array(
      'default' => 'Default',
      'custom' => 'Custom'
    ));
    $f->value = $data['email_alerts_type'];
    $f->showIf = "enable_email_alerts=1";
    $fs->add($f);

    $f = new InputfieldTextarea();
    $f->name = 'email_alerts_msg_custom';
    $f->label = __('Custom Message');
    $f->description = __('Custom email alert message.');
    $f->value = $data['email_alerts_msg_custom'];
    $f->columnWidth = 50;
    $f->showIf = "email_alerts_type=custom,enable_email_alerts=1";
    $fs->add($f);
    $wrap->add($fs);

    // frontend options
    $fs = new InputfieldFieldset();
    $fs->icon = 'sliders';
    $fs->label = __('Frontend Options');
    $fs->description = __('Customize the black hole display.');

    $f = new InputfieldRadios();
    $f->name = 'frontend_warning_msg_type';
    $f->label = __('Warning Message');
    $f->description = __('Type of warning message.');
    $f->columnWidth = 50;
    $f->addOptions(array(
      'default' => 'Default',
      'custom' => 'Custom'
    ));
    $f->value = $data['frontend_warning_msg_type'];
    $fs->add($f);

    $f = new InputfieldTextarea();
    $f->name = 'frontend_warning_msg_custom';
    $f->label = __('Custom Warning Message');
    $f->description = __('Custom warning message for bad bots.');
    $f->value = $data['frontend_warning_msg_custom'];
    $f->columnWidth = 50;
    $f->showIf = "frontend_warning_msg_type=custom";
    $fs->add($f);

    $f = new InputfieldRadios();
    $f->name = 'frontend_banned_msg_type';
    $f->label = __('Banned Message');
    $f->description = __('Type of banned message.');
    $f->columnWidth = 50;
    $f->addOptions(array(
      'default' => 'Default',
      'custom' => 'Custom'
    ));
    $f->value = $data['frontend_banned_msg_type'];
    $fs->add($f);

    $f = new InputfieldTextarea();
    $f->name = 'frontend_banned_msg_custom';
    $f->label = __('Custom Banned Message');
    $f->description = __('Custom banned message for bad bots.');
    $f->value = $data['frontend_banned_msg_custom'];
    $f->columnWidth = 50;
    $f->showIf = "frontend_banned_msg_type=custom";
    $fs->add($f);

    $f = new InputfieldRadios();
    $f->name = 'frontend_show_info';
    $f->label = __('Show Info');
    $f->description = __('Show IP and WHOIS lookup.');
    $f->columnWidth = 100;
    $f->addOptions(array(
      '1' => 'Yes',
      '0' => 'No'
    ));
    $f->value = $data['frontend_show_info'];
    $fs->add($f);
    $wrap->add($fs);

    // advanced settings
    $fs = new InputfieldFieldset();
    $fs->icon = 'cogs';
    $fs->label = __('Advanced Settings');

    $f = new InputfieldTextarea();
    $f->name = 'whitelisted_bots';
    $f->label = __('Whitelisted bots');
    $f->description = __('User Agents that never should be blocked.');
    $f->notes = __('Separate with commas.');
    $f->value = $data['whitelisted_bots'];
    $f->columnWidth = 100;
    $fs->add($f);
    $wrap->add($fs);

    $f = new InputfieldRadios();
    $f->name = 'banned_status_code';
    $f->label = __('Status Code');
    $f->description = __('Status code for all blocked bots.');
    $f->columnWidth = 100;
    $f->addOptions(array(
      'none' => 'None',
      '403' => '403 Forbidden'
    ));
    $f->value = $data['banned_status_code'];
    $fs->add($f);

    // informations
    $fs = new InputfieldFieldset();
    $fs->icon = 'info';
    $fs->label = __('Help, Tips & Tricks');
    $fs->collapsed = Inputfield::collapsedYes;


    $f = new InputfieldMarkup();
    $f->label = __('How to check your access log for excess crawling ?');
    $descriptionmd =
      __("The access log registers all requests Apache (the actual web server software) receives. Analyzing the access log is like following the breadcrumbs to find the villains. An easy way to check for crawler activity using the Apache access log is to use a single Linux command line, take all user agents, and sort them, based on occurrence:  ") .
      '```$cat apache_access.log | awk -F\" \'{print $6}\' | sort | uniq -c | sort -n```';
    $f->markupText =  wire('sanitizer')->entitiesMarkdown($descriptionmd);
    $fs->add($f);

    $f = new InputfieldMarkup();
    $f->label = __('How to limit the Google and Bing crawl rate ?');
    $descriptionmd =
      __('Google (by far) and Bing (to a minor extend) have the most active web crawlers.') . "\n\n" .
      __('They can be over-active if you have a lot of content. To make it worse, both of them do not respect the robots.txt setting for the **crawl-delays**. ') .
      __('You have to adjust their crawl rate manually, using the Google and the Bing web master utilities: ') .
      __('[Googlebot](https://support.google.com/webmasters/answer/48620) / [Bingbot](https://www.bing.com/webmaster/help/crawl-control-55a30302)');
    $f->markupText = wire('sanitizer')->entitiesMarkdown($descriptionmd);
    $f->collapsed = Inputfield::collapsedNo;
    $fs->add($f);

    $wrap->add($fs);

    return $wrap;
  }
}
