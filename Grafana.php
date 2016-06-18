<?php
/**
 * Grafana - this extension add Grafana panels (http://grafana.org) to mediawiki pages
 *
 * -----------------------------------------------------------------------------------
 *
 * Mediawiki extension for Grafana:  Felipe Muñoz Brieva 14.06.2016
 *
 * Installation:
 *
 * To activate this extension, add the following into your LocalSettings.php file:
 * require_once '$IP/extensions/Grafana/Grafana.php';
 *
 * @ingroup Extensions
 * @author Felipe Muñoz Brieva <felipe@delegacionprovincial.es>
 * @version 1.00
 * @link http://www.mediawiki.org/wiki/Extension:Grafana Documentation
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 *
 * Usage:
 *
 *  <Grafana --arguments-- >Header Text</Grafana>
 *
 *  --arguments--:
 *
 *     showheader:        Shows a header with link to Grafana server (yes/no)
 *     urlgrafana:        Grafana website URL
 *     dashboard:         Dashboard name in grafana 
 *     panelposition:     Position of the panel in dashboard (see panelposition note)
 *
 *  --optional arguments--:
 *     var-templatename:  Value of the template param
 *     width:             Panel witdh
 *     height:            Panel height
 *     theme:             Theme name
 *
 *
 * Notes:
 *
 *     "panelposition" is the position of the panel in the dashboard (panelposition=1 it's the first panel in row 1). 
 *     For example if we have 3 rows (row 1 with 2 panels, row 2 with 1 panel and row 3 with 3 panels) 
 *     and we want to select panel 2 in row 3:
 *
 *             panelposition = 5 -> 2 (panels in row 1) + 1 (panels in row 2) + 2 (panel in row 3) 
 *
 *     "var-templatename" If we want to include params for a template we need to add an argument with the prefix "var-"
 *     and templatename. For example if we want to add the var $macrolan:
 *
 *             var-macrolan=template_var_value
 *                
 * Example:
 *
 *  <Grafana showheader=yes urlgrafana=http://grafanaserver dashboard=Dashboard Name panelposition=3 var-macrolan=cma-mal-030>Grafana panel</Grafana>
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *
 * Version 1.00   (14/06/2016):
 *                              - First release
 *               
 */
 
if (!defined( 'MEDIAWIKI' ) ) {
	echo( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
	die( -1 );
}
 
$wgExtensionFunctions[] = "wfGrafanaExtension";

# Grafana extension credits

$wgExtensionCredits['parserhook'][] = array(
        'name' => 'Grafana (version 1.00)',
	'version' => '1.00',
        'author' => 'Felipe Muñoz Brieva (email: felipe@delegacionprovincial.com)',
        'url' => 'http://www.delegacionprovincial.com/mediawiki/index.php?title=Gestion_Online:GrafanaExtension',
        'description' => 'Add Grafana panels to mediawiki pages'
);

# Grafana extension 

function wfGrafanaExtension() {
  global $wgParser;
 
  $wgParser->setHook( "Grafana", "renderGrafana" );
}
 
# Grafana parser

function renderGrafana( $input, array $args, Parser $parser ) {

  // ###### INVALIDATE CACHE ######

  $parser->disableCache();

  // ###### CONSTANTS ######

  $SEARCH=array("  "," ",".","á","é","í","ó","ú","Á","É","Í","Ó","Ú");
  $REPLACE=array(" ","-","","a","e","i","o","u","A","E","I","O","U");

  $PREFIX_VAR          = "var-";
  $COMMIT              = "commit";
  $COMMIT_SHIFT        = 9;
  $COMMIT_SEARCH_TEXT  = 10;
  $YES                 = "yes";
 
  // ###### DEFAULT ######

  $DEFAULT_SHOWHEADER  = $YES;
  $DEFAULT_WIDTH       = "400"; 
  $DEFAULT_HEIGHT      = "250";
 
  $MSG_ERROR_GRAFANA_URL_INICIO = "<br><font color=red>ERROR: (urlGrafana) - Check your URL!!<br>";
  $MSG_ERROR_GRAFANA_URL_FIN    = "</font><br>";
  $MSG_ERROR_EMBEDDED_DATA      = "Error: Embedded data could not be displayed.";

  $MSG_ERROR_GRAFANA_SERVER_1   = "<br><font color=red>Need to check: <br>   a) is Grafana Server running?<br>";
  $MSG_ERROR_GRAFANA_SERVER_2   = "   b) are you running a supported version (v3) ? <br></font><br>";

  // ###### PROXY SETTING ###### Use only if you access to Grafana using a proxy 

  $PROXY_ENABLE        = $NO;                            // $YES/$NO
  $PROXY_HOST          = "proxy_server";                 // Proxy server address
  $PROXY_PORT          = "proxy_port";                   // Proxy server port
  $PROXY_USER          = "user";                         // Username
  $PROXY_PASS          = "password";                     // Password

  if ($PROXY_ENABLE == $YES) {

    $auth = base64_encode("$PROXY_USER:$PROXY_PASS");

    $authContext = array(
      'http' => array(
         'proxy' => "tcp://$PROXY_HOST:$PROXY_PORT",
         'request_fulluri' => true,
         'header' => "Proxy-Authorization: Basic $auth"
       ),
    );

    stream_context_set_default($authContext);

    $cxContext = stream_context_create($authContext);

  }

  // ######  GET PARAMS   ######

  $output="";
  
  $showHeader=$DEFAULT_SHOWHEADER;
  $urlGrafana="";
  $dashboard="";
  $panelTitle="";
  $panelPosition="";
  $width=$DEFAULT_WIDTH; 
  $height=$DEFAULT_HEIGHT;
  $theme="";
  $panelTitle="";

  foreach( $args as $name => $value ){
    switch(strtolower(htmlspecialchars($name))){ 
      case 'showheader':
            $showHeader=htmlspecialchars($value);
            break;
      case 'urlgrafana':
            $urlGrafana=htmlspecialchars($value);
            break;
      case 'dashboard':
            $dashboard=htmlspecialchars($value);
            break;
      case 'panelposition':
            $panelPosition=htmlspecialchars($value);
            break;
      case 'width':
            $width=htmlspecialchars($value);
            break;
      case 'height':
            $height=htmlspecialchars($value);
            break;
      case 'theme':
            $theme=htmlspecialchars($value);
            break;
    }

    if (startsWith(strtolower(htmlspecialchars($name)),$PREFIX_VAR)){ 
        $templateVar[htmlspecialchars($name)]=htmlspecialchars($value);
    }

  }

  $panelTitle=htmlspecialchars($input);

  // ###### GRAFANA VERSION ######

  $grafanaContent = file_get_contents($urlGrafana, False, $cxContext);

  $pos=strpos($grafanaContent, $COMMIT);

  if ($pos > 0){
     $tmpContent=substr($grafanaContent,$pos+$COMMIT_SHIFT,$COMMIT_SEARCH_TEXT);
     $grafanaVersion=split('"', $tmpContent, 2)[0];
  }

  // -------------------------------------------

  $param["showHeader"]=$showHeader; 
  $param["panelTitle"] = $panelTitle; 
  $param["urlGrafana"] = $urlGrafana;; 

  $dashboard = strtolower(str_replace($SEARCH,$REPLACE,$dashboard)); 

  $param["dashboard"] = $dashboard; 
  $param["panelPosition"] = $panelPosition; 
  $param["width"] = $width; 
  $param["height"] = $height; 
  $param["theme"] = $theme; 
  
  // Check: Exists Grafana Server? 

  if(!$grafanaVersion){
      $output.=$MSG_ERROR_GRAFANA_URL_INICIO.$urlGrafana.$MSG_ERROR_GRAFANA_URL_FIN;
      return $output;
  }

  switch (split("\.",$grafanaVersion,2)[0]) {
      case "v3": 
        $output.= Grafana_v3($param,$templateVar);
        break; 
      default: 
        $output.=$MSG_ERROR_GRAFANA_SERVER_1;
        $output.=$MSG_ERROR_GRAFANA_SERVER_2;
  } 
  
  return $output;

}

/* Grafana release 3 */

function Grafana_v3($param,$templateVar) {

  $GRAFANA_URL_PART1   = "dashboard-solo/db";

  global $wgOut, $wgScriptPath; 

  $output="";

  $output.=('<div id="grafana" style="width: '.$param["width"].'px;">');

  // Header for Grafana panel

  if ($param["showHeader"]=="yes"){
      $output.=('<div id="header" style="display:inline;">');
      $output.=('<div id="headerspacer" style="display:inherit;">');
      $output.=('<div id="headershow" style="border:0; display:inherit;">');
      $output.=('<table style="background-color: #F5F5F5; border-collapse: collapse; width: 100%;">');
      $output.=('<tbody>');
      $output.=('<td style="width: 25px; text-align: center; border: 1px #a4a4a4 solid; padding: 2px;">');
      $output.=('<a href='.$param["urlGrafana"].'>');
      $output.=('<img alt="Nagios" src='.$wgScriptPath.'/extensions/Grafana/images/grafana_icon.png>');
      $output.=('</a>');
      $output.=('</td>');
      $output.=('<td style="text-align: center; border: 1px #a4a4a4 solid; padding: 2px;font-size: 18px; color: #314455;">');
      $output.=('<a style="color: black;">'.$param["panelTitle"].'</a>');
      $output.=('</td>');
      $output.=('</tbody>');
      $output.=('</table>');
      $output.=('</div></div></div>');
  }

  // ###### GRAFANA VERSION ######

  $grafanaEmbed=$param["urlGrafana"].'/'.$GRAFANA_URL_PART1.'/'.$param["dashboard"].'?panelId='.$param["panelPosition"];

  for ($i = 0; $i <  count($templateVar); $i++) {
       $key=key($templateVar);
       $val=$templateVar[$key];
       $grafanaEmbed.="&".$key."=".$val;
       next($templateVar);
  }

  if ($param["theme"]){
     $grafanaEmbed.="&theme=".$param["theme"];
  }

  $grafanaEmbed.= '"';
  $grafanaEmbed.=' width="'.$param["width"].'" height="'.$param["height"].' frameborder="0"';

  $output.=('<object data="'.$grafanaEmbed.'">');
  $output.=('<embed src="'.$grafanaEmbed.'">');
  $output.=('</embed>');
  $output.=($MSG_ERROR_EMBEDDED_DATA );
  $output.=('</object>');

  $output.=('</div>');

  return $output;
}

function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}

?>
