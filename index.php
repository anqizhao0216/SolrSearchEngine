<?php
ini_set('memory_limit','8192M');
ini_set('max_execution_time', 300);
include 'SpellCorrector.php';
include('simple_html_dom.php');
//echo SpellCorrector::correct('octabr');
//it will output *october*
// make sure browsers see this page as utf-8 encoded HTML
header('Content-Type: text/html; charset=utf-8');

//Mapping file
$file = fopen('UrlToHtml_Newday.csv','r');
$map = array();
while(($line = fgetcsv($file)) !== FALSE){
    //first element of $line is the name
    //second element of $line is url
    $map[$line[0]] = $line[1];
}
fclose($file);
      
$limit = 10;
$query = isset($_REQUEST['q']) ? $_REQUEST['q'] : false; 
$results = false;

$algorithm =isset($_REQUEST['algorithm']) ? $_REQUEST['algorithm'] : false; 
if ($query){
    // The Apache Solr Client library should be on the include path 
    // which is usually most easily accomplished by placing in the
    // same directory as this script ( . or current directory is a default 
    // php include path entry in the php.ini)
    require_once('Apache/Solr/Service.php');
    
    // create a new solr service instance - host, port, and corename
    // path (all defaults in this example)
    $solr = new Apache_Solr_Service('localhost', 8983, '/solr/myexample/');
    
    // if magic quotes is enabled then stripslashes will be needed
    if (get_magic_quotes_gpc() == 1) {
    $query = stripslashes($query); 
    }
    
    // in production code you'll always want to use a try /catch for any 
    // possible exceptions emitted by searching (i.e. connection
    // problems or a query parsing error)
    try{
        $split = explode(" ", $query);
        $original_query = $query;
        $query = "";
        $flag = 0;
        $fg = isset($_REQUEST['f']) ? true : false; //used to differentiate the instead of xxx
        if($fg == false){
            for($i = 0; $i<sizeof($split);$i++){
                $term = SpellCorrector::correct($split[$i]);
                $query = $query.$term." ";
                if(trim(strtolower($split[$i]) != trim(strtolower($term)))){
                    $flag = 1; //query is not correct
                }
            }
            $query = trim($query);
        }
        else{
            $query = $original_query;
        }

        if($algorithm == "pagerank"){
            $additionalParameters = array(
                'sort'=>'pageRankFile desc'
            );
            $results = $solr->search($query,0,$limit,$additionalParameters);
        }
        else{
            $results = $solr->search($query,0,$limit);
        }
    }
    catch(Exception $e){
    //in production you'd probably log or email this error to an admin
    //and then show a special message to the user but for this example
    //we're going to show the full exception
        die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
    }
}
?>

<html>
    <head>
        <title>Search Engine for USA Today</title>
        <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
        <script src="https://code.jquery.com/jquery-1.12.4.js"></script>
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    </head>
    <body>
       
        <form accept-charset="utf-8" method="get" id="myForm">
            <img src="logo.jpg" style="display:inline; width:120px; height:80px; float:left;"/>
            <input id="q" name="q" type="text" style="margin-top:20px; display:inline; width:400px; height:40px; float:left;" value="<?php echo htmlspecialchars($original_query,ENT_QUOTES,'utf-8');?>"/>
            <input type="radio" name="algorithm" value="lucene" <?php echo ($algorithm =='lucene')? 'checked' : ''  ?> checked style="margin-top:30px; float:left;"> Lucene
            <input type="radio" name="algorithm" value="pagerank" <?php echo ($algorithm =='pagerank')? 'checked' : ''  ?> style="margin-top:30px;"> PageRank
            <input type="submit" name="submit"/>
        </form>
        
        <?php
        
        //display results
        if($results){
            if($flag == 1){ 
        ?>
            <h4 style="margin-left:120px;">Showing results for <a href="http://localhost/~yaxian/solr-php-client-master/572hw5.php?q=<?php echo $query; ?>&algorithm=<?php echo $_REQUEST['algorithm']; ?>&f=true"><?php echo $query;?></a></h4>       
            <p style="margin-left:120px;">Search instead for <a href="http://localhost/~yaxian/solr-php-client-master/572hw5.php?q=<?php echo $original_query; ?>&algorithm=<?php echo $_REQUEST['algorithm']; ?>&f=true"><?php echo $original_query; ?></a></p>
        <?php
            }
            
            
            $total = (int)$results->response->numFound;
            $start = min(1,$total);
            $end = min($limit, $total);
        ?>
            <div style="margin-left:120px;">Results<?php echo $start;?> - <?php echo $end;?> of <?php echo $total;?>:</div>
            <ul style="list-style-type:none; width:900px; margin-left:80px;">
            <?php
                //iterate result documents
                foreach($results->response->docs as $doc)
                {
                    echo "<li style='margin-bottom:30px;'>";
                    $title = "";
                    $url = "";
                    $id = "";
                    $description = "";
                    $path="/Users/yaxian/Sites/USA Today/USA Today/";
                    $local_file = "";
                    
                    foreach($doc as $field => $value){
                        if($field == "id"){
                            $local_file = $value;
                            $id = htmlspecialchars($value, ENT_NOQUOTES, 'utf-8');
                            $id = str_replace($path,"",$id);
                        }
                        if($field == "title"){
                            $title = htmlspecialchars($value, ENT_NOQUOTES, 'utf-8');
                        }
                        if($field == "description"){
                            $description = htmlspecialchars($value, ENT_NOQUOTES, 'utf-8');
                        }
                    }
                    if($title == ""){
                        $title = "No Title";
                    }
                    if($id != ""){
                        $url = $map[$id];
                    }
                    
                    echo "<a style='font-size:18px;' target='_blank' href='{$url}'><b>".$title."</b></a><br/>";
                    echo "<a style='font-size:15px;' target='_blank' href='{$url}'>".$url."</a><br/>";
                    
//                    $content = file_get_contents($local_file);
//                    $content = strip_tags($content);
                    $html = file_get_html($local_file);
                    if($html != null){
                        $temp = $html->find('body',-1);
                        if ($temp == null) continue;
                        $content = $description.".".$temp->plaintext;
                        $content = preg_replace("!\s+!"," ",$content);
//                        $sentences = explode(".", $content);
                        $sentences = preg_split("/(\?|\.|\!)/",$content);
                    
                    
//                        $html = $description.".".$title.".".file_get_contents($local_file);
//                        $html = strip_tags($html);
//                        $sentences = explode(".", $html);
                        $words = explode(" ", $query);
                        $snippet = "";
                        $text = "/";
                        $start_delim = "(?=.*?\b";
                        $end_delim="\b)";
                        foreach($words as $item){
                            $text = $text.$start_delim.$item.$end_delim;
                        }
                        $text = $text."^.*$/i";
                        foreach($sentences as $sentence){
                            $sentence = strip_tags($sentence);
                            if(preg_match($text, $sentence) > 0){
                                if(preg_match("(&gt|&lt|\/|{|}|[|]|\|\%|>|<|:)",$sentence)>0){
                                    continue;
                                }
                                else{
                                    $snippet = $snippet.$sentence;
                                    if(strlen($snippet) > 160){
                                        break;
                                    }
                                }
                            }
                        }
                        if(strlen($snippet)>0 && strlen($snippet) < 5){
                            foreach($sentences as $sentence){
                                $sentence = strip_tags($sentence);
                                foreach($words as $word){
                                    $word = "#".$word."#";
                                    if(preg_match($word, $sentence) > 0){
                                        if(preg_match("(&gt|&lt|\/|{|}|[|]|\|\%|>|<|:)",$sentence)>0){
                                            continue;
                                        }
                                        else{
                                            $snippet = $snippet.$sentence;
                                            if(strlen($snippet) > 160){
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        $remove1 = "Let friends in your social network know what you are reading aboutFacebook Email Twitter Google+ LinkedIn Pinterest";
                        $remove2 = "Post to Facebook";
                        $snippet = str_replace($remove1, "", $snippet);
                        $snippet = str_replace($remove2, "", $snippet);

                        if(strlen($snippet) != 0){
                            echo "...".$snippet."...";
                        }
                        
                    }
                    else{
                        $html = $description.".".file_get_contents($local_file);
                        $html = strip_tags($html);
                        $sentences = explode(".", $html);
//                        $sentences = preg_split("/(.|\?|\!)/",$html);
                        $words = explode(" ", $query);
                        $snippet = "";
                        $text = "/";
                        $start_delim = "(?=.*?\b";
                        $end_delim="\b)";
                        foreach($words as $item){
                            $text = $text.$start_delim.$item.$end_delim;
                        }
                        $text = $text."^.*$/i";
                        foreach($sentences as $sentence){
                            $sentence = strip_tags($sentence);
                            if(preg_match($text, $sentence) > 0){
                                if(preg_match("(&gt|&lt|\/|{|}|[|]|\|\%|>|<|:)",$sentence)>0){
                                    continue;
                                }
                                else{
                                    $snippet = $snippet.$sentence;
                                    if(strlen($snippet) > 160){
                                        break;
                                    }
                                }
                            }
                        }
                        if(strlen($snippet)>0 && strlen($snippet) < 5){
                            foreach($sentences as $sentence){
                                $sentence = strip_tags($sentence);
                                foreach($words as $word){
                                    $word = "#".$word."#";
                                    if(preg_match($word, $sentence) > 0){
                                        if(preg_match("(&gt|&lt|\/|{|}|[|]|\|\%|>|<|:)",$sentence)>0){
                                            continue;
                                        }
                                        else{
                                            $snippet = $snippet.$sentence;
                                            if(strlen($snippet) > 160){
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        $remove1 = "Let friends in your social network know what you are reading aboutFacebook Email Twitter Google+ LinkedIn Pinterest";
                        $remove2 = "Post to Facebook";
                        $snippet = str_replace($remove1, "", $snippet);
                        $snippet = str_replace($remove2, "", $snippet);
                        
                        if(strlen($snippet) != 0){
                            echo "...".$snippet."...";
                        }
                    }
                    if(strlen($snippet) == 0){
                        $query_terms = explode(" ",$query);
                        foreach($query_terms as $q){
                            $q = "~\b".$q."\b~";
                            if(preg_match($q, strtolower($description))){
                                echo "...".$description."...";
                                break;
                            }
                            else{
                                if(preg_match($q, strtolower($title))){
                                    echo "...".$title."...";
                                    break;
                                }
                            }
                        }         
                    }
                    echo "</li>";
                }
        }
            ?>
            </ul>
            
        <script>
            $(function(){
                var URL_PREFIX = "http://localhost:8983/solr/myexample/suggest?q=";
                var URL_SUFFIX ="&wt=json";
                $("#q").autocomplete({
                    source : function(request, response){
                        var input = $("#q").val().toLowerCase().split(" ").pop(-1);
                        var URL = URL_PREFIX + input + URL_SUFFIX;
                        $.ajax({
                            url : URL,
                            success : function(data){
                                var input = $("#q").val().toLowerCase().split(" ").pop(-1);
                                var suggestions = data.suggest.suggest[input].suggestions;
                                suggestions = $.map(suggestions, function(value, index){
                                    var prefix = "";
                                    var query = $("#q").val();
                                    var queries = query.split(" ");
                                    if(queries.length > 1){
                                        var lastIndex = query.lastIndexOf(" ");
                                        prefix = query.substring(0, lastIndex+1).toLowerCase();
                                    }
                                    if(prefix =="" && is_stop_word(value.term)){
                                        return null;
                                    }
                                    if(!/^[0-9a-zA-Z]+$/.test(value.term)){
                                        return null;
                                    }
                                    return prefix+value.term;
                                });
                                response(suggestions.slice(0,5));
                            },
                            dataType: 'jsonp',
                            jsonp: 'json.wrf'
                        });
                    },
                    minLength: 1
                });
            });
        
            function is_stop_word(word){
                var regex = new RegExp("\\b" + word + "\\b", "i");
                return stopWords.search(regex) < 0 ? false : true;
            }
        
        </script>
        
        <script>
            var stopWords = "a,able,about,above,across,after,all,almost,also,am,among,can,an,and,any,are,as,at,be,because,been,but,by,cannot,could,dear,did,do,does,either,else,ever,every,for,from,get,got,had,has,have,he,her,hers,him,his,how,however,i,if,in,into,is,it,its,just,least,let,like,likely,may,me,might,most,must,my,neither,no,nor,not,of,off,often,on,only,or,other,our,own,rather,said,say,says,she,should,since,so,some,than,that,the,their,them,then,there,these,they,this,tis,to,too,twas,us,wants,was,we,were,what,when,where,which,while,who,whom,why,will,with,would,yet,you,your,not";
        </script>
    </body>
</html>











