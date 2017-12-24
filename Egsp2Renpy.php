<?php
/**
 * @fileOverview Egsp2Renpy
 * hirot.org「Ensemble Girls Story Pretender」の
 * シナリオを勝手にRen'Py(6.99)で読み込める形式に変換します。
 * 
 * ※注意※
 * urllistのURLにアクセスしますので、
 * hirot.orgに負荷をかけない程度にご使用ください。
 * 
 * ※他に必要なファイル※
 * ・あんさんぶるガールズ！用のRen'pyプロジェクト(命令定義済み EGSPメニュー対応版)
 * 
 * @author hatotank.net
 * @version 1.0 2016/02/21
 */
//error_reporting(E_ALL & ~E_NOTICE);
$inFile = "urllist.txt"; // 直リンクURLリスト(コンバート対象)
$out_scenario_dir = "egsp_scenario/"; // コンバート出力先
$out_csv = "egsp_list.csv"; // リストCSV
$egsp_blackout_flg = true; // 暗転処理をEGSP準拠にする場合は「true」、しない場合は「false」
$bgm_flg = false; // story_normal.mp3を使用する場合は「true」、しない場合は「false」
$bg_zorder = 9999; // 背景z-order

$version = "1.0";
$tab = "    ";
$fp_list;

function convertRenpy($in_url,$in_label,$in_idx)
{
    $bs = array();
    $bg = array();
    $ch = array();
    $ch_img = array();
    $bg_img = array();
    $pos = array();
    $scenes = array();
    $mes = array();
    $prev_scenes;
    $script;
    $clear_flg = false;
    $blackout_reverse = 1;
    $zorder = 1;
    
    global $version,$tab,$bgm_flg,$egsp_blackout_flg,$out_scenario_dir,$fp_list,$bg_zorder;
    
    $dom = new DOMDocument;
    @$dom->loadHTMLFile(str_replace(array("\r\n", "\r", "\n"),"",$in_url));
    $xpath = new DOMXPath($dom);
    
    // script
    foreach($xpath->query('//script') as $node){
        $script = explode("\n",$node->nodeValue);
    }
    
    // Preload
    foreach($xpath->query('//body//img') as $node){
        if(strpos($node->getAttribute('id'), "storyPreload") !== false){
            $t = explode("/",str_replace("http://hirot.org/kmsk/","",$node->getAttribute('src')));
            $img_file = array_pop($t);
            $img_path = implode("/",$t) . "/";
            $exp_key = explode(".",$img_file)[0];
            $id = explode("_",$exp_key)[0];
            
            if(strpos($node->getAttribute('id'), "storyPreloadB") !== false){  
                $bg = $bg + array($node->getAttribute('id')=>array('id'=>$id,'key'=>$exp_key,'img'=>$img_file,'path'=>$img_path));
            }else{
                $ch = $ch + array($node->getAttribute('id')=>array('id'=>$id,'key'=>$exp_key,'img'=>$img_file,'path'=>$img_path));
            }
        }
    }
    
    // script(pos)
    foreach($ch as $k => $v){
        if(strpos($k,'storyPreloadF') !== false){
            foreach($script as $line){
                if(strpos($line,'storyPreloadF') !== false && strpos($line,$k) !== false){
                    preg_match_all("/'\d+'/",$line,$o);
                    $pos = $pos + array($k=>array('x'=>str_replace("'","",$o[0][0]),'y'=>str_replace("'","",$o[0][1])));
                    break;
                }
            }
        }
    }
    
    // convert start
    $scenes[] = "#Egsp2Renpy Ver{$version}\n";
    
    // img_char
    foreach($ch as $k => $v){
        $id = $v["id"];
        $img = $v["img"];
        $key = $v["key"];
        $path = $v["path"];
        
        if(strpos($k,'storyPreloadF') !== false){
            $pos_x = $pos[$k]["x"];
            $pos_y = $pos[$k]["y"];
            $scenes[] = "image c{$key} = im.Composite((520,1000),(0,0),\"{$path}{$id}"."_base.png\",({$pos_x},{$pos_y}),\"{$path}{$img}\")\n";
            $scenes[] = "image c{$key}_false = im.MatrixColor(im.Composite((520,1000),(0,0),\"{$path}{$id}"."_base.png\",({$pos_x},{$pos_y}),\"{$path}{$img}\"),im.matrix.brightness(-0.2))\n";            
        }else{
            $scenes[] = "image c{$key} = \"{$path}{$img}\"\n";
            $scenes[] = "image c{$key}_false = im.MatrixColor(\"{$path}{$img}\",im.matrix.brightness(-0.2))\n";
            $bs = $bs + array($id=>$k);
        }
        $ch_img = $ch_img + array("{$k}"=>"c{$key}");
        $ch_img = $ch_img + array("{$k}_false"=>"c{$key}_false");
    }
    
    // img_bg
    foreach($bg as $k => $v){
        $id = $v["id"];
        $img = $v["img"];
        $key = $v["key"];
        $path = $v["path"];
        
        $scenes[] = "image bg{$key} = \"{$path}{$img}\"\n";
        $bg_img = $bg_img + array("{$k}"=>"bg{$key}");
    }
    
    // scenario start
    $scenes[] = "\n";
    $scenes[] = "label L_egsp_{$in_label}:\n";
    
    $search_str = array("setTimeout(function(){","setCHR(","setEXP(","setMSG(","moveCHR(","setCover(",");","}","'");
    
    $show_pos = array('L'=>' at left2','C'=>' at center2','R'=>' at right2');
    $show_img = array('L'=>'','C'=>'','R'=>'');
    $show_img_prev = array('L'=>'','C'=>'','R'=>'');
    $show_down = array('L'=>'','C'=>'','R'=>'');
    $show_up = array('L'=>'down','C'=>'down','R'=>'down');
    $show_order = array('L'=>0,'C'=>0,'R'=>0);
    $show_bg = "";
    $last_pos = "";
    $blackout_flg = false;
    $show_up_flg = false;
    $init_flg = false;
    $setCover_flg = false;
    // 
    $index = 0;
    foreach($script as $line){
        
        // start
        if(strpos($line,'init') !== false){
            // メニューからのBGM用
            $scenes[] = "{$tab}stop music fadeout 1.0\n";
            $init_flg = true;
        }
        
        // title
        if(strpos($line,'setCover') !== false){
            $t = explode(",",str_replace($search_str,"",str_replace("[","[[",trim($line))));
            if($bgm_flg == true){
                $scenes[] = "{$tab}stop music fadeout 1.0\n";
            }
            if($blackout_flg == true){
                $scenes[] = "{$tab}show blackout at tf_mv_black_l zorder {$bg_zorder}\n";
                $scenes[] = "{$tab}scene black\n";
                $scenes[] = "{$tab}with wipeleft2\n";
                $scenes[] = "{$tab}pause(0.8)\n";
                $scenes[] = "{$tab}hide blackout\n";
                
                $scenes[] = "{$tab}window hide\n";
                $scenes[] = "{$tab}show background\n";
                $scenes[] = "{$tab}show bg_book at bookrotate\n";
                $scenes[] = "{$tab}pause(1.0)\n"; 
            }else{
                $scenes[] = "{$tab}scene black with dissolve\n";
                $scenes[] = "{$tab}window hide\n";
                $scenes[] = "{$tab}show background\n";
                $scenes[] = "{$tab}show bg_book at bookrotate\n";
                $scenes[] = "{$tab}pause(1.0)\n";
            }
            
            $scenes[] = "{$tab}call screen subtitle(\"${t[0]}\",\"{$t[1]}\",\"{$t[2]}\")\n";
            if($bgm_flg){
                $scenes[] = "{$tab}play music \"bgm/story_normal.mp3\" loop\n";
            }
            $scenes[] = "{$tab}hide bg_book\n";
            $scenes[] = "{$tab}hide background\n";
            $setCover_flg = true;
            
            if($init_flg){
                $line = "L_egsp_{$in_label},{$t[0]},9,{$in_idx}\n";
                $mbstr = mb_convert_encoding($line, "UTF-8", "auto");
                fwrite($fp_list,$mbstr);
                $init_flg = false;
            }
        }
        
        // clear
        if(strpos($line,'clear') !== false){
            $scenes[] = "{$tab}scene\n";
            $scenes[] = "{$tab}show black\n"; // follow
            
            $show_img['R'] = "";
            $show_img['C'] = "";
            $show_img['L'] = "";
            $clear_flg = true;
        }
        
        // blackout start
        if(strpos($line,'blackout(\'s\')') !== false){
            $blackout_flg = true;
        }
        
        // blackout end
        if(strpos($line,'blackout(\'e\')') !== false){
            if($show_bg != ""){
                $scenes[] = "{$tab}show {$bg_img[$show_bg]}\n";
            }else{
                $scenes[] = "{$tab}show black\n";
            }
            if($setCover_flg != true){
                if($blackout_reverse == 1){
                    $scenes[] = "{$tab}show blackout at tf_mv_black_l zorder {$bg_zorder}\n";
                    $scenes[] = "{$tab}with wipeleft2\n";
                }else{
                    $scenes[] = "{$tab}show blackout at tf_mv_black_r zorder {$bg_zorder}\n";
                    $scenes[] = "{$tab}with wiperight2\n";
                }
                $scenes[] = "{$tab}pause(0.8)\n";
                $scenes[] = "{$tab}hide blackout\n";
                
                if($egsp_blackout_flg){
                    $blackout_reverse = -$blackout_reverse;    
                }
            }
            $blackout_flg = false;
            $setCover_flg = false;
        }
        
        // bg
        if(strpos($line,'setBG') !== false){
            foreach($bg as $k => $v){
                if(strpos($line,$v["img"]) !== false){
                    if($show_bg != ""){
                        $scenes[] = "{$tab}hide {$bg_img[$show_bg]}\n";
                    }
                    $show_bg = $k;
                    if($blackout_flg == false){
                        $scenes[] = "{$tab}show {$bg_img[$show_bg]}\n";
                    }
                    break;
                }
            }
        }
        
        // moveCHR
        if(strpos($line,'moveCHR') !== false){
            $t = explode(",",str_replace($search_str,"",trim($line)));
            switch($t[1]){
                case 'OUT':
                    $show_img[$t[0]] = "";
                    break;
                case 'UP':
                    $show_up['L'] = "down";
                    $show_up['C'] = "down";
                    $show_up['R'] = "down";
                    $show_up[$t[0]] = "up";
                    $last_pos = $t[0];
                    $show_order[$t[0]] = ++$zorder;
                    break;
                case 'DOWN':
                    $show_up[$t[0]] = "down";
                    $force_down_flg = true;
                    $show_order[$t[0]] = ++$zorder;
                    break;
                default:
                    $show_img[$t[1]] = $show_img[$t[0]];
                    $show_img[$t[0]] = "";
                    $show_order[$t[1]] = ++$zorder;
                    break;
            }
            $show_up_flg = true;
        }
        
        // setCHR
        if(strpos($line,'setCHR') !== false){
            $t = explode(",",str_replace($search_str,"",trim($line)));
            $show_img[$t[1]] = $t[0];
            $show_up[$t[1]] = "up";
            $show_up_flg = true;
            $last_pos = $t[1];
            $show_order[$t[1]] = ++$zorder;
        }
        
        // setEXP
        if(strpos($line,'setEXP') !== false){
            $t = explode(",",str_replace($search_str,"",trim($line)));
            if($t[0] != ""){
                $show_img[$t[1]] = $t[0];
            }else{
                //空白はベースで補う
                $t2 = str_replace("_false","",$show_img[$t[1]]);
                $show_img[$t[1]] = $bs[$ch[$t2]["id"]];
            }
            $show_up[$t[1]] = "up";
            $show_up_flg = true;
            $last_pos = $t[1];
            $show_order[$t[1]] = ++$zorder;
        }
        
        // setMSG
        if(strpos($line,'setMSG') !== false){
            $t = explode(",",str_replace($search_str,"",str_replace("[","[[",trim($line))));
            if($t[0] == ""){
                $t[0] = " ";
            }
            $mes[] = "{$tab}\"$t[0]\" \"$t[1]\"\n";
            $mes[] = "{$tab}\n";
        }
        
        // end
        if(strpos($line,'close') !== false){
            $scenes[] = "{$tab}window hide\n";
            $scenes[] = "{$tab}scene black with fade2\n";
            if($bgm_flg){
                $scenes[] = "{$tab}stop music fadeout 1.0\n";
            }
            $scenes[] = "{$tab}return\n";
        }
        
        // scenes
        if(strpos($line,'break') !== false){
            // down
            if($show_up_flg){
                $last_pos_cnt = 0;
                if($show_up['L'] == "up"){ $last_pos_cnt++; }
                if($show_up['C'] == "up"){ $last_pos_cnt++; }
                if($show_up['R'] == "up"){ $last_pos_cnt++; }
                if($last_pos_cnt > 1){
                    $show_up['L'] = "down";
                    $show_up['C'] = "down";
                    $show_up['R'] = "down";
                    $show_up[$last_pos] = "up";
                }
                
                if($show_up['L'] == "down" && $show_img['L'] != ""){
                    if(strpos($show_img['L'],'_false') === false){
                        $show_img['L'] = "{$show_img['L']}_false";
                    }
                }else if($show_up['L'] == "up" && $show_img['L'] != ""){
                    $t2 = str_replace("_false","",$show_img['L']);
                    $show_img['L'] = $t2; 
                }
                
                if($show_up['C'] == "down" && $show_img['C'] != ""){
                    if(strpos($show_img['C'],'_false') === false){
                        $show_img['C'] = "{$show_img['C']}_false";
                    }
                }else if($show_up['C'] == "up" && $show_img['C'] != ""){
                    $t2 = str_replace("_false","",$show_img['C']);
                    $show_img['C'] = $t2;  
                }
                
                if($show_up['R'] == "down" && $show_img['R'] != ""){
                    if(strpos($show_img['R'],'_false') === false){
                        $show_img['R'] = "{$show_img['R']}_false";
                    }
                }else if($show_up['R'] == "up" && $show_img['R'] != ""){
                    $t2 = str_replace("_false","",$show_img['R']);
                    $show_img['R'] = $t2;
                }
            }
            
            // hide
            if($show_img['L'] != $show_img_prev['L'] || $clear_flg){
                if($show_img_prev['L'] != ""){
                    $scenes[] = "{$tab}hide {$ch_img[$show_img_prev['L']]}\n";
                    if($clear_flg){
                        $show_img_prev['L'] = "";
                    }
                }
            }
            if($show_img['C'] != $show_img_prev['C'] || $clear_flg){
                if($show_img_prev['C'] != ""){
                    $scenes[] = "{$tab}hide {$ch_img[$show_img_prev['C']]}\n";
                    if($clear_flg){
                        $show_img_prev['C'] = "";
                    }
                }
            }
            if($show_img['R'] != $show_img_prev['R'] || $clear_flg){
                if($show_img_prev['R'] != ""){
                    $scenes[] = "{$tab}hide {$ch_img[$show_img_prev['R']]}\n";
                    if($clear_flg){
                        $show_img_prev['R'] = "";
                    }
                }
            }
            
            // show
            if($show_img['L'] != $show_img_prev['L']){
                if($show_img['L'] != ""){
                    $scenes[] = "{$tab}show {$ch_img[$show_img['L']]} at left2 zorder {$show_order['L']}\n";
                }
            }
            if($show_img['C'] != $show_img_prev['C']){
                if($show_img['C'] != ""){
                    $scenes[] = "{$tab}show {$ch_img[$show_img['C']]} at center2 zorder {$show_order['C']}\n";
                }
            }
            if($show_img['R'] != $show_img_prev['R']){
                if($show_img['R'] != ""){
                    $scenes[] = "{$tab}show {$ch_img[$show_img['R']]} at right2 zorder {$show_order['R']}\n";
                }
            }
            if(isset($mes)){
                foreach($mes as $v){
                    $scenes[] = "$v";
                }
            }
            unset($mes);
            $clear_flg = false;
            $show_img_prev = $show_img;
            $show_up = array('L'=>'down','C'=>'down','R'=>'down');
            $show_up_flg = false;
            $last_pos = "";
            $force_down_flg = false;
            $setCover_flg = false;
            $index++;
        }
    }
    
    // init → setCover が見つからない場合は強制的に出力
    if($init_flg){
        $line = "L_egsp_{$in_label},タイトル無し({$in_label}),9,{$in_idx}\n";
        $mbstr = mb_convert_encoding($line, "UTF-8", "auto");
        fwrite($fp_list,$mbstr);
        $init_flg = false;   
    }
    
    $fp_rpy = fopen("{$out_scenario_dir}{$in_label}.rpy","w");
    foreach($scenes as $v){
        $mbstr = mb_convert_encoding($v, "UTF-8", "auto");
        fwrite($fp_rpy,$mbstr);
    }
    fclose($fp_rpy);
}

//main
$fp = fopen($inFile,"r");
if($fp){
    $index = 1;
    // output scenario(*.rpy)
    // output egsp_list.csv
    $fp_list = fopen("{$out_scenario_dir}{$out_csv}", "w");
    if($fp_list){
        while(($buf = fgets($fp)) !== false){
            // #が存在するとコメントとして扱う(手抜き)
            if(strpos($buf,"#") !== false){
                continue;
            }
            if(strpos($buf,"http") !== false){
                $t = parse_url(str_replace(array("\r\n", "\r", "\n"),"",$buf));
                $t = explode("/",$t['path']);
                if(end($t) == ""){
                    $t2 = $index;
                }else{
                    $t2 = end($t);
                }
                convertRenpy($buf,$t2,$index);
            }else{
                // ファイルと判定
                $t = explode(".",$buf);
                convertRenpy($buf,$t[0],$index);
            }
            $index++;
        }
    }
    fclose($fp_list);
}
fclose($fp);
?>
