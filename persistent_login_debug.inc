<?php
// $Id$


/**
 * Implementation of hook_block().
 *
 * WARNING!  The Persistent Login debug block is extremely dangerous.
 * It gives away access to every user account on the site that has a
 * persistent login session.  Don't use it.
 */
function persistent_login_block($op = 'list', $delta = 0, $edit = array()) {
  switch ($op) {
    case 'list':
      $blocks[0] = array('info' => t('Persistent Login debug'));
      return $blocks;

    case 'view':
      $block = array(
        'subject' => t('Persistent Login'),
        'content' => _persistent_login_debug(),
      );
      return $block;
  }
}

function _persistent_login_debug() {
  $output = ('<div class="error">Security Hole!  Debugging only. Turn this off!</div>');

  global $user;
  $now = time();
  $output .= ('<p>Time: '. format_date($now) ." ($now)<br />". 'Uid: '. $user->uid .'</p>');

  $output .= '<p>Cookies<br />';
  foreach ($_COOKIE as $name => $value) {
    $output .= "$name = $value<br />";
  }

  if (!empty($_COOKIE[PERSISTENT_LOGIN_COOKIE])) {
    list($uid, $series, $token) = explode(':', $_COOKIE[PERSISTENT_LOGIN_COOKIE]);
    $res = db_query('SELECT * FROM {persistent_login} pl WHERE pl.uid=%d', $uid);
    $rows = array();
    while ($r = db_fetch_array($res)) {
      $row = array();
      $row[] = $r['uid'];
      $row[] = $r['series'];
      $row[] = $r['token'];
      $row[] = date('m/d H:i', $r['expires']);
      $rows[] = $row;
    }
    $output .= '<p>Stored data:<br />';
    $output .= theme('table', array('uid', 'series', 'token', 'expires'), $rows);
  }
  
  $vars = array('check', 'login', 'reauth', 'default_user', 'welcomed');
  foreach ($vars as $var) {
    if (!empty($_SESSION['persistent_login_'. $var])) {
      $output .= ("persistent_login_$var: ". serialize($_SESSION['persistent_login_'. $var]) ."<br />");
    }
  }

  return $output;
}
