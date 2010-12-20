<?php

  /*   mnoGoSearch-php-lite v.3.3.5
  *   for mnoGoSearch - free web search engine
  *   (C) 2001-2007 by Sergey Kartashoff <gluke@mail.ru>,
  *               mnoGoSearch Developers Team <devel@mnogosearch.org>
  */


  require_once('search.class.php');

  // maximal page number to view
  $MAX_NP=1000;

  // maximum results per page
  $MAX_PS=100;


  /* variables section */

  $dbaddr='mysql://dbuser:dbpass@localhost:3306/mnogosearch/?dbmode=blob'; 

  $localcharset='iso-8859-1';
  $browsercharset='iso-8859-1';
  $cache=$crosswords='no';
  $ispelluseprefixes=$trackquery='no';
  $spell_host=$vardir=$datadir='';

  $hlbeg='<span style="background-color: #ff0; color: #f00; font-weight: bold;">';
  $hlend='</span>';

  $affix_file=array();
  $spell_file=array();
  $stopwordfile_arr=array();
  $synonym_arr=array();

  // $affix_file['en']='/opt/udm/ispell/en.aff';
  // $affix_file['ru']='/opt/udm/ispell/ru.aff';
  // $spell_file['en']='/opt/udm/ispell/en.dict';
  // $spell_file['ru']='/opt/udm/ispell/ru.dict';
  // $stopwordfile_arr[]='stopwords.txt';
  // $synonym_arr[]='/opt/udm/synonym/english.syn';

  $minwordlength=1;
  $maxwordlength=32;


  /* initialisation section */

  $self='';
  $QUERY_STRING= isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
  $REMOTE_ADDR= isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

  $ps= isset($_GET['ps']) ? $_GET['ps'] : 20; // results per page
  $np= isset($_GET['np']) ? $_GET['np'] : 0;  // page number
  $wm= isset($_GET['wm']) ? $_GET['wm'] : ''; // Word match: wrd (whole word), beg (beginning), end (endin), sub (subsstring)
  $wf= isset($_GET['wf']) ? $_GET['wf'] : ''; // Sections: VALUE="222211" >all sections,  220000" >Description, 202000" >Keywords, 200200" >Title,  200010" >Body

  $m=  isset($_GET['m']) ? $_GET['m'] : 'all'; // Match type: any, all, bool
  $o=  isset($_GET['o']) ? $_GET['o'] : 0;
  $dt=  isset($_GET['dt']) ? $_GET['dt'] : ''; // limit via datetime: back, er, range
  $dp=  isset($_GET['dp']) ? $_GET['dp'] : 0;  // dt=back, days: 0 (anytime), 10M, 1h, 7d, 14d, 1m  etc
  $dx=  isset($_GET['dx']) ? $_GET['dx'] : 0;  // dt=er: 1 (after), -1 ()before)
  $dy=  isset($_GET['dy']) ? $_GET['dy'] : 0;  // dt=er, month: 0 (January)-11
  $dm=  isset($_GET['dm']) ? $_GET['dm'] : 0;  // dt=er, day of month: 1-31
  $dd=  isset($_GET['dd']) ? $_GET['dd'] : 0;  // dt=er, year 4 digit (2001, etc)
  $db=  isset($_GET['db']) ? $_GET['db'] : ''; // dt=range, date beginning: 01/01/1970
  $de=  isset($_GET['de']) ? $_GET['de'] : ''; // dt=range, date ending: 01/01/2020


  $ul=  isset($_GET['ul']) ? $_GET['ul'] : '';
  $t=  isset($_GET['t']) ? $_GET['t'] : '';
  $lang=  isset($_GET['lang']) ? $_GET['lang'] : '';
  $q=  isset($_GET['q']) ? $_GET['q'] : '';
  $cat=  isset($_GET['cat']) ? $_GET['cat'] : '';

  $t_DY='';
  $t_CS='';
  $t_CP='';

  if (($dt!='back') && ($dt!='er') && ($dt!='range')) $dt='back';
  if ($dp=="") $dp=0;
  if (($dx!=0) && ($dx!=-1) && ($dx!=1)) $dx=0;
  if ($dy<1970) $dy=1970;
  if (($dm<0) || ($dm>11)) $dm=0;
  if (($dd<=0) || ($dd>31)) $dd="01";

  $db=urldecode($db);
  $de=urldecode($de);

  if ($db=="") $db='01/01/1970';
  if ($de=="") $de='31/12/2020';


  if (isset($q)) {
    $q=urldecode($q);
    $have_query_flag=1;
  } else {
    $have_query_flag=0;
  }

  $ul=urldecode($ul);
  $tag=urldecode($t); 
  $lang=urldecode($lang); 

  $query_orig=$q;

  if (isset($CHARSET_SAVED_QUERY_STRING)) {
    $q_local=urldecode($CHARSET_SAVED_QUERY_STRING);
    if (preg_match('/q=([^&]*)\&/',$q_local,$param)) {
      $q_local=urlencode($param[1]);
    } elseif (preg_match('/q=(.*)$/',$q_local,$param)) {
      $q_local=urlencode($param[1]);
    } else {
      $q_local=urlencode($q);
    }
  } else {
    $q_local=urlencode($q);
  }

  $ul_local=urlencode($ul);
  $t_local=urlencode($tag);
  $db_local=urlencode($db);
  $de_local=urlencode($de);
  $lang_local=urlencode($lang);

  if (($MAX_NP > 0) && ($np>$MAX_NP)) $np=$MAX_NP;
  if (($MAX_PS > 0) && ($ps>$MAX_PS)) $ps=$MAX_PS;


  // -----------------------------------------------
  //  M A I N 
  // -----------------------------------------------

  if (preg_match("/^(\d+)\.(\d+)\.(\d+)/",phpversion(),$param)) {
    $phpver=$param[1];
    if ($param[2] < 9) {
      $phpver .= "0$param[2]";
    } else {
      $phpver .= "$param[2]";
    }
    if ($param[3] < 9) {
      $phpver .= "0$param[3]";
    } else {
      $phpver .= "$param[3]";
    }
  } else {
    print "Cannot determine php version: <b>".phpversion()."</b>\n";
    exit;
  }

  $have_spell_flag=0;

  $udm = new msearchAgent();
  //  $udm_agent=Udm_Alloc_Agent($dbaddr);  

  if (isset($_GET['cc']))
  {
    $udm->ParseDocText($QUERY_STRING);
    $res= $udm->storeDocCGI();

    printf("<BASE HREF=\"%s\">\n", $_GET["URL"]);
    printf("<table border=\"1\" cellpadding=\"2\" cellspacing=\"0\"><tr>\n");
    printf("<td><b>Document ID:</b> %s</td>\n", $udm->get_param_ex('ID'));
    printf("<td><b>Last modified:</b> %s</td>\n", $udm->get_param_ex('Last-Modified'));
    printf("<td><b>Language:</b> %s</td>\n", $udm->get_param_ex('Content-Language'));
    printf("<td><b>Charset:</b> %s</td>\n", $udm->get_param_ex('Charset'));
    printf("<td><b>Size:</b> %s</td>\n", $udm->get_param_ex('Content-Length'));
    printf("</tr></table>\n<hr>\n");
    printf("%s", $udm->ParseDocText($udm->get_param_ex('document')));
    print_bottom_banner();
    exit;
  }


  /*  
  // @todo: convert to object oriented
  if ($phpver >= 40006) {
  if ($temp_cp_arr=Udm_Cat_Path($udm_agent,$cat)) {
  reset($temp_cp_arr);
  $temp_cp='';
  for ($i=0; $i<count($temp_cp_arr); $i+=2) {
  $cp_path=$temp_cp_arr[$i];
  $cp_name=$temp_cp_arr[$i+1];
  $temp_cp .= " &gt; <a href=\"$self?cat=$cp_path\">$cp_name</a> ";
  }
  $t_CP=$temp_cp;
  }

  if ($temp_cp_arr=Udm_Cat_List($udm_agent,$cat)) {
  reset($temp_cp_arr);
  $temp_cp='';
  for ($i=0; $i<count($temp_cp_arr); $i+=2) {
  $cp_path=$temp_cp_arr[$i];
  $cp_name=$temp_cp_arr[$i+1];
  $temp_cp .= "<a href=\"$self?cat=$cp_path\">$cp_name</a><br>";
  }
  $t_CS=$temp_cp;
  }

  if (isset($category) && $temp_cp_arr=Udm_Cat_Path($udm_agent,$category))
  {
  reset($temp_cp_arr);
  $temp_cp='';
  for ($i=0; $i<count($temp_cp_arr); $i+=2)
  {
  $cp_path=$temp_cp_arr[$i];
  $cp_name=$temp_cp_arr[$i+1];
  $temp_cp .= " &gt; <a href=\"$self?cat=$cp_path\">$cp_name</a> ";
  }
  $t_DY=$temp_cp;
  }
  }
  // @todo: convert to class
  */

  $udm->init($ps, $np);
  $udm->setDetectClones(UDM_DISABLED);

  $udm->enableTrackQuery(strtolower($trackquery) == 'yes');
  $udm->enableCache(strtolower($cache) == 'yes');
  $udm->enableIspellUserPrefixes(strtolower($ispelluseprefixes) == 'yes');
  $udm->enableCrosswords(strtolower($crosswords)== 'yes');
  $udm->setLocalCharset($localcharset);
  $udm->setBrowserCharset($browsercharset, true);

  // Highlighting taken care of in class automatically
  //  Udm_Set_Agent_Param($udm_agent,UDM_PARAM_HLBEG,$hlbeg);  
  //  Udm_Set_Agent_Param($udm_agent,UDM_PARAM_HLEND,$hlend);  

  /*
  // @todo complete
  for ($i=0; $i < count($stopwordfile_arr); $i++) {
  if ($stopwordfile_arr[$i] != '') {
  Udm_Set_Agent_Param($udm_agent,UDM_PARAM_STOPFILE,$stopwordfile_arr[$i]);
  }
  }

  for ($i=0; $i < count($synonym_arr); $i++)
  {
  if ($synonym_arr[$i] != '')
  {
  Udm_Set_Agent_Param($udm_agent,UDM_PARAM_SYNONYM,$synonym_arr[$i]);
  }
  }
  */  

  $udm->setQueryString($QUERY_STRING);
  $udm->setQueryString($REMOTE_ADDR);

  if ($have_query_flag) $udm->set_param(UDM_PARAM_QUERY,$query_orig);

  $udm->setSearchMode($m);
  $udm->setWordMatchMode($wm);

  $udm->setMinWordLen($minwordlength);
  $udm->setMaxWordLen($maxwordlength);

  $udm->setDir($vardir, $datadir);

  $udm->setWeightFactor($wf);
  $udm->setUrlLimit($ul);
  $udm->setTagLimit($tag);
  $udm->setCategoryLimit($cat);
  $udm->setLangLimit($lang);

  // @todo: place in class
  if (($dt == 'back') && ($dp != '0')) {
    $recent_time=format_dp($dp);
    if ($recent_time != 0) {
      $dl=time()-$recent_time;
      $udm->addDateLimit(">$dl");      
    }
  } elseif ($dt=='er') {
    $recent_time=mktime(0,0,0,($dm+1),$dd,$dy);
    if ($dx == -1) {
      $udm->addDateLimit("<$recent_time");
    } elseif ($dx == 1) {
      $udm->addDateLimit(">$recent_time");
    }
  } elseif ($dt=='range') {
    $begin_time=format_userdate($db);
    if ($begin_time)       $udm->addDateLimit(">$begin_time");

    $end_time=format_userdate($de);
    if ($end_time)       $udm->addDateLimit("<$end_time");
  }

?>
<HTML>
<HEAD>
  <TITLE>mnoGoSearch: <? echo HtmlSpecialChars(StripSlashes($query_orig)); ?></TITLE>
</HEAD>

<body BGCOLOR="#FFFFFF" LINK="#0050A0" VLINK="#0050A0" ALINK="#0050A0">
<center>

  <FORM METHOD=GET ACTION="<? echo $self; ?>">
    <table bgcolor=#eeeee0 border=0 width=100%>
      <tr><td>
          <BR>
          <INPUT TYPE="hidden" NAME="ps" VALUE="10">
          Search for: <INPUT TYPE="text" NAME="q" SIZE=50 VALUE="<? echo HtmlSpecialChars(StripSlashes($query_orig)); ?>">
          <INPUT TYPE="submit" VALUE="Search!"><BR>

          Results per page:
          <SELECT NAME="ps">
            <OPTION VALUE="10" <? if ($ps==10) echo 'SELECTED';?>>10
            <OPTION VALUE="20" <? if ($ps==20) echo 'SELECTED';?>>20
            <OPTION VALUE="50" <? if ($ps==50) echo 'SELECTED';?>>50
          </SELECT>

          Match:
          <SELECT NAME="m">
            <OPTION VALUE="all" <? if ($m=='all') echo 'SELECTED';?>>All
            <OPTION VALUE="any" <? if ($m=='any') echo 'SELECTED';?>>Any
            <OPTION VALUE="bool" <? if ($m=='bool') echo 'SELECTED';?>>Boolean
          </SELECT>


          Search for:
          <SELECT NAME="wm">
            <OPTION VALUE="wrd" <? if ($wm=='wrd') echo 'SELECTED';?>>Whole word
            <OPTION VALUE="beg" <? if ($wm=='beg') echo 'SELECTED';?>>Beginning
            <OPTION VALUE="end" <? if ($wm=='end') echo 'SELECTED';?>>Ending
            <OPTION VALUE="sub" <? if ($wm=='sub') echo 'SELECTED';?>>Substring
          </SELECT>

          <br>

          Search through:
          <SELECT NAME="ul">
            <OPTION VALUE="" <? if ($ul=='') echo 'SELECTED';?>>Entire site
            <OPTION VALUE="/docs/" <? if ($ul=='/docs/') echo 'SELECTED';?>>Docs
            <OPTION VALUE="/files/" <? if ($ul=='/files') echo 'SELECTED';?>>Files
            <OPTION VALUE="/servers/" <? if ($ul=='/servers/') echo 'SELECTED';?>>Servers
          </SELECT>

          in:
          <SELECT NAME="wf">
            <OPTION VALUE="222211" <? if ($wf=='222211') echo 'SELECTED';?>>all sections
            <OPTION VALUE="220000" <? if ($wf=='220000') echo 'SELECTED';?>>Description
            <OPTION VALUE="202000" <? if ($wf=='202000') echo 'SELECTED';?>>Keywords
            <OPTION VALUE="200200" <? if ($wf=='200200') echo 'SELECTED';?>>Title
            <OPTION VALUE="200010" <? if ($wf=='200010') echo 'SELECTED';?>>Body
          </SELECT>

          Language:
          <SELECT NAME="lang">
            <OPTION VALUE="" <? if ($lang=='222211') echo 'SELECTED';?>>Any
            <OPTION VALUE="en" <? if ($lang=='en') echo 'SELECTED';?>>English
            <OPTION VALUE="ru" <? if ($lang=='ru') echo 'SELECTED';?>>Russian
          </SELECT>

          Restrict search:
          <SELECT NAME="t">
            <OPTION VALUE="" <? if ($t=='') echo 'SELECTED';?>>All sites
            <OPTION VALUE="AA" <? if ($t=='AA') echo 'SELECTED';?>>Sport
            <OPTION VALUE="BB" <? if ($t=='BB') echo 'SELECTED';?>>Shopping
            <OPTION VALUE="CC" <? if ($t=='CC') echo 'SELECTED';?>>Internet
          </SELECT>

        </td></tr>

      <!-- 'search with time limits' options -->
      <TR><TD>
          <TABLE CELLPADDING=2 CELLSPACING=0 BORDER=0>
            <CAPTION>
              Limit results to pages published within a specified period of time.<BR>
              <FONT SIZE=-1><I>(Please select only one option)</I></FONT>
            </CAPTION>
            <TR> 
              <TD VALIGN=center><INPUT TYPE=radio NAME="dt" VALUE="back" <? if ($dt=='back') echo 'checked';?>></TD>
              <TD><SELECT NAME="dp">
                  <OPTION VALUE="0" <? if ($dp=='0') echo 'SELECTED';?>>anytime
                  <OPTION VALUE="10M" <? if ($dp=='10M') echo 'SELECTED';?>>in the last ten minutes
                  <OPTION VALUE="1h" <? if ($dp=='1h') echo 'SELECTED';?>>in the last hour
                  <OPTION VALUE="7d" <? if ($dp=='7d') echo 'SELECTED';?>>in the last week
                  <OPTION VALUE="14d" <? if ($dp=='14d') echo 'SELECTED';?>>in the last 2 weeks
                  <OPTION VALUE="1m" <? if ($dp=='1m') echo 'SELECTED';?>>in the last month
                </SELECT>
              </TD>
            </TR>
            <TR>
              <TD VALIGN=center><INPUT type=radio NAME="dt" VALUE="er" <? if ($dt=='er') echo 'checked';?>>
              </TD>
              <TD><SELECT NAME="dx">
                  <OPTION VALUE="1" <? if ($dx=='1') echo 'SELECTED';?>>After
                  <OPTION VALUE="-1" <? if ($dx=='-1') echo 'SELECTED';?>>Before
                </SELECT>

                or on

                <SELECT NAME="dm">
                  <OPTION VALUE="0" <? if ($dm=='0') echo 'SELECTED';?>>January
                  <OPTION VALUE="1" <? if ($dm=='1') echo 'SELECTED';?>>February
                  <OPTION VALUE="2" <? if ($dm=='2') echo 'SELECTED';?>>March
                  <OPTION VALUE="3" <? if ($dm=='3') echo 'SELECTED';?>>April
                  <OPTION VALUE="4" <? if ($dm=='4') echo 'SELECTED';?>>May
                  <OPTION VALUE="5" <? if ($dm=='5') echo 'SELECTED';?>>June
                  <OPTION VALUE="6" <? if ($dm=='6') echo 'SELECTED';?>>July
                  <OPTION VALUE="7" <? if ($dm=='7') echo 'SELECTED';?>>August
                  <OPTION VALUE="8" <? if ($dm=='8') echo 'SELECTED';?>>September
                  <OPTION VALUE="9" <? if ($dm=='9') echo 'SELECTED';?>>October
                  <OPTION VALUE="10" <? if ($dm=='10') echo 'SELECTED';?>>November
                  <OPTION VALUE="11" <? if ($dm=='11') echo 'SELECTED';?>>December
                </SELECT>
                <INPUT TYPE=text NAME="dd" VALUE="<? echo $dd; ?>" SIZE=2 maxlength=2>
                ,
                <SELECT NAME="dy" >
                  <OPTION VALUE="2000" <? if ($dy=='2000') echo 'SELECTED';?>>2000
                  <OPTION VALUE="2001" <? if ($dy=='2001') echo 'SELECTED';?>>2001
                  <OPTION VALUE="2002" <? if ($dy=='2002') echo 'SELECTED';?>>2002
                  <OPTION VALUE="2003" <? if ($dy=='2003') echo 'SELECTED';?>>2003
                  <OPTION VALUE="2004" <? if ($dy=='2004') echo 'SELECTED';?>>2004
                  <OPTION VALUE="2005" <? if ($dy=='2005') echo 'SELECTED';?>>2005
                  <OPTION VALUE="2006" <? if ($dy=='2006') echo 'SELECTED';?>>2006
                  <OPTION VALUE="2007" <? if ($dy=='2007') echo 'SELECTED';?>>2007
                </SELECT>
              </TD>
            </TR>
            <TR>
              <TD VALIGN=center><INPUT TYPE=radio NAME="dt" VALUE="range" <? if ($dt=='range') echo 'checked';?>>
              </TD>
              <TD>
                Between
                <INPUT TYPE=text NAME="db" VALUE="<? echo $db; ?>" SIZE=11 MAXLENGTH=11>
                and
                <INPUT TYPE=text NAME="de" VALUE="<? echo $de; ?>" SIZE=11 MAXLENGTH=11>
              </TD>
            </TR>
          </TABLE>

        </TD></TR>
      <!-- end of stl options -->

      <!-- categories stuff -->
      <tr><td><? echo $t_CP; ?></td></tr>
      <tr><td><? echo $t_CS; ?></td></tr>
      <input type=hidden name=cat value="<? echo $cat; ?>">
      <!-- categories stuff end -->

    </table>
  </form>
</center>


<?php
/* 
// @todo: convert 
reset($affix_file);
while (list($t_lang,$file)=each($affix_file))
{
if (! Udm_Load_Ispell_Data($udm_agent,UDM_ISPELL_TYPE_AFFIX,$t_lang,$file,0))
{
print_error_local("Error loading ispell data from file");
}
else
$have_spell_flag=1;

$temp= $spell_file[$t_lang];
for ($i=0; $i<count($temp); $i++)
{
if (! Udm_Load_Ispell_Data($udm_agent,UDM_ISPELL_TYPE_SPELL,$t_lang,$temp[$i],1))
{
print_error_local("Error loading ispell data from file");
}
else
$have_spell_flag=1;
} 
}
*/
if (! $have_query_flag) {
  print_bottom();
  return;  
} elseif ($have_query_flag && ($q=='')) {
  print ("<FONT COLOR=red>You should give at least one word to search for.</FONT>\n");   
  print_bottom();
  return;
}         

try {
  $results = $udm->query($q);
} catch (Exception $e) {
  print_error_local($e->getMessage());
  exit();
}  

$found = $results->found;
if (!$found) {
  print ("Search Time: {$results->searchtime}<br>Search results:\n");
  print ("<small>{$results->wordinfo}</small><HR><CENTER>Sorry, but search returned no results.<P>\n");
  print ("<I>Try to produce less restrictive search query.</I></CENTER>\n");

  print_bottom();
  return;
} 

$from=IntVal($np)*IntVal($ps); 
$to=IntVal($np+1)*IntVal($ps); 

if($to>$found) $to=$found;
if (($from+$ps)<$found) $isnext=1;
$nav=make_nav($query_orig);

print("Search Time: {$results->searchtime}<br>Search results: <small>{$results->wordinfo}</small><HR>\n");
print("Displaying documents {$results->first_doc}-{$results->last_doc} of total <B>{$results->found}</B> found.\n");

$rows = $results->get_results();
foreach ($rows as $item) {

  print ("<DL><DT><b>$item[ndoc].</b><a href=\"$item[url]\" TARGET=\"_blank\"><b>$item[title]</b></a>\n");
  print ("[<b>$item[rating]</b>]<DD>\n");
  print ("<table width=\"60%\"><tbody><tr><td>\n");
  print ("<small>\n");
  print (($item['desc'] != '')?$item['desc']:$item['text']."...<BR>$t_DY\n");
  print ("</small>\n");
  print ("<UL><LI><small>\n");
  print ("<A HREF=\"$item[url]\" TARGET=\"_blank\">$item[url]</A>\n");
  print ('<font color="#008800">');
  print ("- $item[docsize] bytes - $item[lastmod] [{$item['contype']}]");
  if ($item['stored_href'] != '')
    printf('<DD><small>[<a href="%s" TARGET="_blank">Cached copy</a>]</small></DD>', $item['stored_href']);
  print ("</font>");
  print ("</small></LI></UL>\n");
  print ("</tr></tbody></table>\n");
  print ("</DL>\n");
}  

print("<HR><CENTER> $nav </CENTER>\n");    
print_bottom();

// classes are freed when out of scope, so we're done!

/// =========================

// -----------------------------------------------
// print_bottom()
// -----------------------------------------------
function print_bottom_banner(){
  print ("<HR><center><img src=\"http://www.mnogosearch.org/img/mnogo.gif\">\n");
  print ("<font size=\"-1\">Powered by <a href=\"http://www.mnogosearch.org/\">mnoGoSearch</a></font><br>\n<p>\n");
}

function print_bottom(){
  print_bottom_banner();
  print ("</BODY></HTML>\n");
}


// -----------------------------------------------
// print_error_local($str)
// -----------------------------------------------
function print_error_local($str){
  print ("<CENTER><FONT COLOR=\"#FF0000\">An error occured!</FONT>\n");
  print ("<P><B>$str</B></CENTER>\n");
  print_bottom();    
  exit;
}

// -----------------------------------------------
// exit_local()
// -----------------------------------------------
function exit_local($print_err = 1) {
  drop_temp_table($print_err);
  exit;
}


// -----------------------------------------------
// format_dp($dp)
// -----------------------------------------------
function format_dp($dp) {
  $result=0;

  while ($dp != '') {    
    if (preg_match('/^([\-\+]?\d+)([sMhdmy]?)/',$dp,$param)) {      
      switch ($param[2]) {
        case 's':  $multiplier=1; break;
        case 'M':  $multiplier=60; break;
        case 'h':  $multiplier=3600; break;
        case 'd':  $multiplier=3600*24; break;
        case 'm':  $multiplier=3600*24*31; break;
        case 'y':  $multiplier=3600*24*365; break;
        default: $multiplier=1;
      }

      $result += $param[1]*$multiplier;
      $dp=preg_replace("/^[\-\+]?\d+$param[2]/",'',$dp);
    } else {
      return 0;
    }
  }

  return $result;
}

// -----------------------------------------------
// format_userdate($date)
// -----------------------------------------------
function format_userdate($date)
{
  $result=0;
  if (preg_match('/^(\d+)\/(\d+)\/(\d+)$/',$date,$param))
  {
    $day=$param[1];
    $mon=$param[2];
    $year=$param[3];
    $result=mktime(0,0,0,$mon,$day,$year);
  }
  return $result;
}

// -----------------------------------------------
// ParseDocText($text)
// -----------------------------------------------
//function ParseDocText($text)
//{
//  global $hlbeg, $hlend;
//  $str= $text;
//  $str= str_replace("\2",$hlbeg,$str);
//  $str= str_replace("\3",$hlend,$str);
//  return $str;
//}


// -----------------------------------------------
// make_nav($query_orig)
// -----------------------------------------------
function make_nav($query_orig){
  global $found,$np,$isnext,$ps,$tag,$ul,$self,$o,$m,$cat;
  global $dt, $dp, $dx, $dm, $dy, $dd, $db, $de, $lang, $wm, $wf;
  global $q_local,$ul_local,$t_local,$db_local,$de_local,$lang_local;

  if($np>0){
    $prevp=$np-1;
    $prev_href="$self?q=$q_local&np=$prevp&m=$m".
    ($ps==20?'':"&ps=$ps").
    ($tag==''?'':"&t=$t_local").
    ($ul==''?'':"&ul=$ul_local").
    ($wm==''?'':"&wm=$wm").
    ($wf==''?'':"&wf=$wf").
    (!$o?'':"&o=$o").
    ($dt=='back'?'':"&dt=$dt").
    (!$dp?'':"&dp=$dp").
    (!$dx?'':"&dx=$dx").
    ($dd=='01'?'':"&dd=$dd").
    (!$dm?'':"&dm=$dm").
    ($dy=='1970'?'':"&dy=$dy").
    ($db=='01/01/1970'?'':"&db=$db_local").
    ($de=='31/12/2020'?'':"&de=$de_local").
    ($cat==''?'':"&cat=$cat").
    ($lang==''?'':"&lang=$lang_local");

    $nav_left="<TD><A HREF=\"$prev_href\">Prev</A></TD>\n";
  } elseif ($np==0) {
    $nav_left="<TD><FONT COLOR=\"#707070\">Prev</FONT></TD>\n";
  }

  if($isnext==1) {
    $nextp=$np+1;
    $next_href="$self?q=$q_local&np=$nextp&m=$m".
    ($ps==20?'':"&ps=$ps").
    ($tag==''?'':"&t=$t_local").
    ($ul==''?'':"&ul=$ul_local").
    ($wm==''?'':"&wm=$wm").
    ($wf==''?'':"&wf=$wf").
    (!$o?'':"&o=$o").
    ($dt=='back'?'':"&dt=$dt").
    (!$dp?'':"&dp=$dp").
    (!$dx?'':"&dx=$dx").
    ($dd=='01'?'':"&dd=$dd").
    (!$dm?'':"&dm=$dm").
    ($dy=='1970'?'':"&dy=$dy").
    ($db=='01/01/1970'?'':"&db=$db_local").
    ($de=='31/12/2020'?'':"&de=$de_local").
    ($cat==''?'':"&cat=$cat").
    ($lang==''?'':"&lang=$lang_local");

    $nav_right="<TD><A HREF=\"$next_href\">Next</TD>\n";
  } else {
    $nav_right="<TD><FONT COLOR=\"#707070\">Next</FONT></TD>\n";
  }

  $nav_bar='';
  $nav_bar0='<TD>$NP</TD>';
  $nav_bar1='<TD><A HREF="$NH">$NP</A></TD>';

  $tp=ceil($found/$ps);

  $cp=$np+1;

  if ($cp>5) {
    $lp=$cp-5;
  } else {
    $lp=1;
  }

  $rp=$lp+10-1;
  if ($rp>$tp) {
    $rp=$tp;
    $lp=$rp-10+1;
    if ($lp<1) $lp=1;
  }


  if ($lp!=$rp) {
    for ($i=$lp; $i<=$rp;$i++) {
      $realp=$i-1;

      if ($i==$cp) {
        $nav_bar=$nav_bar.$nav_bar0;
      } else {
        $nav_bar=$nav_bar.$nav_bar1;
      }

      $href="$self?q=$q_local&np=$realp&m=$m".
      ($ps==20?'':"&ps=$ps").
      ($tag==''?'':"&t=$t_local").
      ($ul==''?'':"&ul=$ul_local").
      ($wm==''?'':"&wm=$wm").
      ($wf==''?'':"&wf=$wf").
      (!$o?'':"&o=$o").
      ($dt=='back'?'':"&dt=$dt").
      (!$dp?'':"&dp=$dp").
      (!$dx?'':"&dx=$dx").
      ($dd=='01'?'':"&dd=$dd").
      (!$dm?'':"&dm=$dm").
      ($dy=='1970'?'':"&dy=$dy").
      ($db=='01/01/1970'?'':"&db=$db_local").
      ($de=='31/12/2020'?'':"&de=$de_local").
      ($cat==''?'':"&cat=$cat").
      ($lang==''?'':"&lang=$lang_local");

      $nav_bar=ereg_replace('\$NP',"$i",$nav_bar);
      $nav_bar=ereg_replace('\$NH',"$href",$nav_bar);
    }

    $nav="<TABLE BORDER=0><TR>$nav_left $nav_bar $nav_right</TR></TABLE>\n";
  } elseif ($found) {
    $nav="<TABLE BORDER=0><TR>$nav_left $nav_right</TR></TABLE>\n";
  }

  return $nav;
}
