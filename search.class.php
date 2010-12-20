<?php

if (!extension_loaded('mnogosearch')) {
  print "<b>This script requires PHP 4.0.5+ with mnoGoSearch extension</b>";
  exit;
}

//$x = new msearchAgent();
//$r = $x->query('joomla');
//print_r($r);

class msearchResults {
  var $msearchAgent;
  var $res;    
  var $rows;
  var $wordinfo;
  var $searchtime;
  var $first_doc;
  var $last_doc;

  function __construct($res, $msearchAgent) {
    $this->msearchAgent = $msearchAgent;
    $this->res = $res;
    $this->found=Udm_Get_Res_Param($res,UDM_PARAM_FOUND);
    $this->rows=Udm_Get_Res_Param($res,UDM_PARAM_NUM_ROWS);
    $this->wordinfo=Udm_Get_Res_Param($res,UDM_PARAM_WORDINFO);
    $this->searchtime=Udm_Get_Res_Param($res,UDM_PARAM_SEARCHTIME);
    $this->first_doc=Udm_Get_Res_Param($res,UDM_PARAM_FIRST_DOC);
    $this->last_doc=Udm_Get_Res_Param($res,UDM_PARAM_LAST_DOC);      
  }
  
  function get_results() {
    $res = $this->res;
    $data = array();

    for($i=0;$i<$this->rows;$i++){
      $r = array();
      
      $r['count'] = $i;
      $r['ndoc']=Udm_Get_Res_Field($res,$i,UDM_FIELD_ORDER);
      $r['rating']=Udm_Get_Res_Field($res,$i,UDM_FIELD_RATING);
      $r['url']=Udm_Get_Res_Field($res,$i,UDM_FIELD_URL);
      $r['contype']=Udm_Get_Res_Field($res,$i,UDM_FIELD_CONTENT);
      $r['docsize']=Udm_Get_Res_Field($res,$i,UDM_FIELD_SIZE);
      $r['lastmod']=Udm_Get_Res_Field($res,$i,UDM_FIELD_MODIFIED);

      $title=Udm_Get_Res_Field($res,$i,UDM_FIELD_TITLE);   
      $title=($title) ? ($title):'No title';    
      $r['title']=$this->msearchAgent->ParseDocText($title);

      $r['text'] = $this->msearchAgent->ParseDocText(Udm_Get_Res_Field($res,$i,UDM_FIELD_TEXT));
      $r['keyw']= $this->msearchAgent->ParseDocText(Udm_Get_Res_Field($res,$i,UDM_FIELD_KEYWORDS));
      $r['desc']= $this->msearchAgent->ParseDocText(Udm_Get_Res_Field($res,$i,UDM_FIELD_DESC));

      $r['crc']=Udm_Get_Res_Field($res,$i,UDM_FIELD_CRC);
      $r['rec_id']=Udm_Get_Res_Field($res,$i,UDM_FIELD_URLID);

      $r['doclang']=Udm_Get_Res_Field($res,$i,UDM_FIELD_LANG);
      $r['doccharset']=Udm_Get_Res_Field($res,$i,UDM_FIELD_CHARSET); 
      $r['category']=Udm_Get_Res_Field($res,$i,UDM_FIELD_CATEGORY);
      $r['stored_href'] = Udm_Get_Res_Field_Ex($res, $i, "stored_href");

      $data[] = $r;
    }

    return $data;
  }

  function __destruct() {
    Udm_Free_Res($this->res);
    $this->res = null;
  }
}

class msearchAgent {

  var $agent;
  var $have_spell_flag = false;
  var $hlbeg='<span style="background-color: #ff0; color: #f00; font-weight: bold;">';
  var $hlend='</span>';

  function __construct($dbaddr = 'mysql://joomla:joomla@localhost:3306/mnogosearch/?dbmode=blob') {
    $this->agent = Udm_Alloc_Agent($dbaddr);
    
    //                     $udm_agent=Udm_Alloc_Agent($dbaddr);  
  }

  function __destruct() {
    if ($this->have_spell_flag) Udm_Free_Ispell_Data($this->agent);
    Udm_Free_Agent($this->agent);
    $this->agent = null;
  }

  function init($ps = 10, $np = 0) {
    $this->setDetectClones();
    $this->setPageSize($ps);
    $this->setPageNumber($np);    
    $this->setHighlight($this->hlbeg, $this->hlend);    
  }
  
  function query($q) {
    // Udm_Set_Agent_Param_Ex($this->agent, 'q', 'test');          

    $res=Udm_Find($this->agent,$q);  
    $errno = Udm_Errno($this->agent);
    if ($errno) {
      throw new Exception(Udm_Error($this->agent), $errno);
    }

    $resultSet = new msearchResults($res, $this);
    return $resultSet;
  }

  // -----------------------------------------------
  // ParseDocText($text)
  // -----------------------------------------------
  function ParseDocText($text)
  {
    $str= $text;
    $str= str_replace("\2",$this->hlbeg,$str);
    $str= str_replace("\3",$this->hlend,$str);
    return $str;
  }
  
  function parseQueryString($QUERY_STRING) {
    return udm_parse_query_string($this->agent, $QUERY_STRING);
  }
  
  function storeDocCGI() {
    return udm_store_doc_cgi($this->agent); 
  }

  function get_param($param) {
    return udm_get_agent_param($this->agent, $param);
  }
  
  function get_param_ex($param) {
    return udm_get_agent_param_ex($this->agent, $param);
  }
  
  
  function set_param($name, $value) {
    return Udm_Set_Agent_Param($this->agent,$name, $value);
  }
  
  function set_params($params) {
    foreach ($params as $n => $v) {
      $this->set_param($n, $v);
    }
  }

  function setDetectClones($value = UDM_DISABLED) {
    $this->set_param(UDM_PARAM_DETECT_CLONES,UDM_DISABLED);
  }

  function setPageSize($ps = 10) {
    $this->set_param(UDM_PARAM_PAGE_SIZE, $ps);    
  }

  function setPageNumber($np = 0) {
    $this->set_param(UDM_PARAM_PAGE_NUM,$np);
  }

  function enableTrackQuery($bool = true) {
    if ($bool) {
      $this->set_param(UDM_PARAM_TRACK_MODE,UDM_TRACK_ENABLED);
    } else {
      $this->set_param(UDM_PARAM_TRACK_MODE,UDM_TRACK_DISABLED);
    }
  }

  function enableCache($bool = true) {
    if ($bool) {
      $this->set_param(UDM_PARAM_CACHE_MODE,UDM_CACHE_ENABLED);
    } else {
      $this->set_param(UDM_PARAM_CACHE_MODE,UDM_CACHE_DISABLED);
    }
  }

  function enableIspellUserPrefixes($bool = true) {
    if ($bool) {
      $this->set_param(UDM_PARAM_ISPELL_PREFIXES,UDM_PREFIXES_ENABLED);
    } else {
      $this->set_param(UDM_PARAM_ISPELL_PREFIXES,UDM_PREFIXES_DISABLED);
    }
  }

  function enableCrosswords($bool = true) {
    if ($bool) {
      $this->set_param(UDM_PARAM_CROSS_WORDS,UDM_CROSS_WORDS_ENABLED);
    } else {
      $this->set_param(UDM_PARAM_CROSS_WORDS,UDM_CROSS_WORDS_DISABLED);
    }
  }

  function setLocalCharset($localcharset = '') {
    if ($localcharset != '')
    {
      $this->set_param(UDM_PARAM_CHARSET,$localcharset);
    }
  }

  function setBrowserCharset($browsercharset = '', $echo = true) {
    if ($browsercharset != '')
    {
      $this->set_param(UDM_PARAM_BROWSER_CHARSET,$browsercharset);
      if ($echo)  Header ("Content-Type: text/html; charset=$browsercharset");
    }
  }

  function setHighlight($hlbeg, $hlend) {
    $this->hlbeg = $hlbeg;
    $this->hlend = $hlend;
    $this->set_param(UDM_PARAM_HLBEG,$hlbeg);  
    $this->set_param(UDM_PARAM_HLEND,$hlend);  
  }    

  function setQueryString($QUERY_STRING) {
    $this->set_param(UDM_PARAM_QSTRING,$QUERY_STRING);    
  }

  function setRemoteAddress($REMOTE_ADDR) {
    $this->set_param(UDM_PARAM_REMOTE_ADDR,$REMOTE_ADDR);    
  }

  function setSearchMode($m = 'all') {
    switch($m) {
      case 'any':  $this->set_param(UDM_PARAM_SEARCH_MODE,UDM_MODE_ANY); break;
      case 'bool': $this->set_param(UDM_PARAM_SEARCH_MODE,UDM_MODE_BOOL); break;
      case 'all': 
      default: $this->set_param(UDM_PARAM_SEARCH_MODE,UDM_MODE_ALL); 
    }
  }

  function setWordMatchMode($wm) {
    switch($wm) {
      case 'beg': $this->set_param(UDM_PARAM_WORD_MATCH,UDM_MATCH_BEGIN); break;
      case 'end': $this->set_param(UDM_PARAM_WORD_MATCH,UDM_MATCH_END); break;
      case 'sub': $this->set_param(UDM_PARAM_WORD_MATCH,UDM_MATCH_SUBSTR); break;
      case 'wrd': 
      default:    $this->set_param(UDM_PARAM_WORD_MATCH,UDM_MATCH_WORD); break;
    }
  }

  function setMinWordLen($minwordlength) {
    if ($minwordlength >= 0) 
      $this->set_param(UDM_PARAM_MIN_WORD_LEN,$minwordlength);
  }

  function setMaxWordLen($maxwordlength) {
    if ($maxwordlength >= 0) 
      $this->set_param(UDM_PARAM_MAX_WORD_LEN,$maxwordlength);  
  }

  function setDir($vardir = '', $datadir = '') {
    if ($vardir) $this->set_param(UDM_PARAM_VARDIR,$vardir);
    if ($datadir) $this->set_param(UDM_PARAM_VARDIR,$datadir);     
  }

  function setWeightFactor($wf) {
    if ($wf != '') 
      $this->set_param(UDM_PARAM_WEIGHT_FACTOR,$wf);
  }

  function setUrlLimit($ul) {
    if ($ul != '')
      $this->set_param($udm_agent,UDM_LIMIT_URL,$ul);
  }

  function setTagLimit($tag) {   
    if ($tag != '') $this->set_param(UDM_LIMIT_TAG,$tag);
  }

  function setCategoryLimit($cat) {
    if ($cat != '') $this->set_param(UDM_LIMIT_CAT,$cat);
  }

  function setLangLimit($lang) {
    if ($lang != '') $this->set_param(UDM_LIMIT_LANG,$lang);
  }

  function addDateLimit($time) {
    if ($time != '') Udm_Add_Search_Limit($udm_agent,UDM_LIMIT_DATE, $time); 
  }

  // if ($have_query_flag)Udm_Set_Agent_Param($udm_agent,UDM_PARAM_QUERY,$query_orig);   

}