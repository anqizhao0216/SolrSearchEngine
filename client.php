<?php
include 'SpellCorrector.php';
include 'simple_html_dom.php';
ini_set('memory_limit', '8192M');

// make sure browsers see this page as utf-8 encoded HTML
header('Content-Type: text/html; charset=utf-8');

$limit = 10;
$query = isset($_REQUEST['q']) ? $_REQUEST['q'] : false;
$algorithm = isset($_REQUEST['algorithm']) ? $_REQUEST['algorithm'] : false;
$results = false;

$file = fopen("UrlToHtml_Newday.csv", "r");
while ($data = fgetcsv($file, 0, ",")) {
  $arr[$data[0]] = $data[1];
}
fclose($file);


if ($query)
{
  // The Apache Solr Client library should be on the include path
  // which is usually most easily accomplished by placing in the
  // same directory as this script ( . or current directory is a default
  // php include path entry in the php.ini)
  require_once('Apache/Solr/Service.php');

  // create a new solr service instance - host, port, and webapp
  // path (all defaults in this example)
  $solr = new Apache_Solr_Service('localhost', 8983, '/solr/myexample');

  // if magic quotes is enabled then stripslashes will be needed
  if (get_magic_quotes_gpc() == 1)
  {
    $query = stripslashes($query);
  }


  // in production code you'll always want to use a try /catch for any
  // possible exceptions emitted  by searching (i.e. connection
  // problems or a query parsing error)
  try
  {
    $query_terms_correct = array_values(array_filter(explode(" ", $query)));
    $final_correct_phrase = "";
    $position = 0;
    foreach($query_terms_correct as $single_term) {
        $position++;
        $final_correct_phrase .= SpellCorrector::correct($single_term);
        if ($position != count($query_terms_correct)) {
           $final_correct_phrase .= " ";
        }

    }
    $correctQuery = $final_correct_phrase;

    // echo $correctQuery;
    if ($algorithm) {
        if ($_REQUEST['algorithm'] == "pageRank") {
          $additionalPara = array('sort'=>'pageRankFile desc');
          $results = $solr->search($query, 0, $limit, $additionalPara);
        } else {
           $results = $solr->search($query, 0, $limit);
        }
    } else {
        $results = $solr->search($query, 0, $limit);
    }


  }
  catch (Exception $e)
  {
    // in production you'd probably log or email this error to an admin
    // and then show a special message to the user but for this example
    // we're going to show the full exception
    die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
  }

}

?>
<html>
  <head>
    <title>PHP Solr Client Example</title>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="/resources/demos/style.css">
    <script src="https://code.jquery.com/jquery-1.12.4.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script type="text/javascript">
    $(function() {
      $("#q").autocomplete({
          source : function(request, response){
              var input_query = $("#q").val().toLowerCase().split(" ");
              var results = [];
              var prefix = "";
              for (var i = 0; i < input_query.length; i++) {
                var term = input_query[i];
                prefix += term;
                prefix += " ";
                console.log(prefix);
                var query_term = $("#q").val().toLowerCase().split(" ").pop(-1);
                if (term.trim().length !== 0) {
                  $.ajax({
                    url : "http://localhost:8983/solr/myexample/suggest?q=" + query_term + "&wt=json",
                    success : function(data){
                        var return_data = JSON.parse(JSON.stringify(data.suggest.suggest));
                        var suggest_arr = return_data[term].suggestions;
                        for (var j = 0; j < suggest_arr.length; j++) {
                          prefix_arr = prefix.toLowerCase().split(" ");
                          prefix = prefix_arr.slice(0, i-1).toString();
                          results.push(prefix + " " + suggest_arr[j].term);
                        }
                        response(results.slice(0, 5)); // ???
                    },
                    dataType: 'jsonp',
                    jsonp: 'json.wrf'
                  });
                }

              }
            }
          });
          minLength: 1
        });
    </script>
  </head>
  <body>
    <form  accept-charset="utf-8" method="get">
      <label for="q">Search:</label>
      <input id="q" name="q" type="text" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'utf-8'); ?>"/>
      <input type="submit"/>
      <br>
      <input type="radio" name="algorithm" value="Lucene" <?php if ($algorithm && $_REQUEST['algorithm'] == "Lucene") { echo "checked"; }?> /  checked>Lucene
      <input type="radio" name="algorithm" value="pageRank" <?php if ($algorithm && $_REQUEST['algorithm'] == "pageRank") { echo "checked"; }?>/>PageRank
    </form>
<?php

// display results
if ($results)
{
  $total = (int) $results->response->numFound;
  $start = min(1, $total);
  $end = min($limit, $total);
?>
<?php
  if ($correctQuery != $query) {
    $query = $correctQuery;
?>
    Did you mean <a href="client.php?q=<?php echo $query; ?>&algorithm=<?php echo $algorithm; ?>"><?php echo $query; ?></a>?

<?php
  }
?>
    <div>Results <?php echo $start; ?> - <?php echo $end;?> of <?php echo $total; ?>:</div>
    <ol>
<?php

  // iterate result documents
  foreach ($results->response->docs as $doc)
  {
?>
      <li>
        <table style="border: 1px solid black; text-align: left">
<?php
    // $arr = array_map('str_getcsv', file('UrlToHtml_Newday.csv'));
    $webLink = "N/A";
    $id = "N/A";
    $des = "N/A";
    $title = "N/A";

    // iterate document fields / values
    foreach ($doc as $field => $value) {
        if ($field == "og_url") {
          if ($value == '$fbLink') {
            $webLink = $arr[$docid];
          } else {
            $webLink = $value;
          }

        }
        if ($field == "og_description") {
          $des = $value;
        }
        if ($field == "id") {
          $snippet = "";
          $html_file = str_get_html(file_get_contents($value));
          $text = str_replace(array("\'", "!", "?", ",", '\"', "&amp", "&"), "", strtolower($html_file->plaintext));
          $text = preg_replace('/\s+/', '_', $text);

          $tokens = array_values(array_filter(explode("_", $text)));
          // echo $html_file->plaintext;
          $query_terms = array_values(array_filter(explode(" ", $query)));
          $index = 0;
          $flag = false;
          $s_query = "";
          while (!$flag && $index < count($query_terms)) {
            // echo "the query term I'm looking for is".$query_terms[$index];
            $s = array_search($query_terms[$index], $tokens);
            if ($s) {
              $s_query = $query_terms[$index];
              $flag = true;
              // echo "the tokens[$s] == ".$tokens[$s];
            }
            $index++;
          }

          $s -= 10;

          if ($e > count($tokens)) {
            $e = count($tokens) - 1;
          }

          if ($s < 0) {
            $s = 0;
          }

          $e = $s + 20;
          $count = false;
          // echo "the start token = ".$tokens[$s];
          if ($s < $e) {
            for ($i = $s; $i < $e; $i++) {
              $added = false;
              foreach($query_terms as $term) {
                if (strtolower($term) === strtolower($tokens[$i])) {
                  if (strlen($snippet) > 160) {
                    break;
                  }
                  $snippet .=" <b>".$tokens[$i]."</b>";
                  $added = true;
                  $count = true;
                  break;
                }
              }
              if (strlen($snippet) > 160) {
                    break;
                  }
              if ($added == true) {
                continue;
              }
              $snippet .=" ".htmlspecialchars($tokens[$i], ENT_NOQUOTES, 'utf-8');
              // $snippet .=" ".$tokens[$i];
            }
            $snippet = "...".$snippet."...";
            $snippet = preg_replace("/\<b>$s_query\<b>/i", "<B>$s_query</B>", $snippet);
          }
          // $snippet = "php is the best language in the world <b>hello world!</b>";
          // echo $snippet;

          $docid = substr($value, 57);
          $id = $value;
        }
        if ($field == "title") {
          $title = $value;
        }
    }
    if ($webLink == "N/A") {
      $webLink = $arr[$docid];
    }

    if ($count == false) {
      $snippet = $des;
    }
?>
    <tr>
      <th>Title</th>
      <td><a href=<?php echo "$webLink"?> target="_blank"><?php echo htmlspecialchars($title, ENT_NOQUOTES, 'utf-8'); ?></td>
    </tr>
    <tr>
      <th>URL</th>
      <td><a href=<?php echo "$webLink"?> target="_blank"><?php echo htmlspecialchars($webLink, ENT_NOQUOTES, 'utf-8'); ?></td>
    </tr>
    <tr>
      <th>ID</th>
      <td><?php echo htmlspecialchars($id, ENT_NOQUOTES, 'utf-8'); ?></td>
    </tr>
    <tr>
      <th>Snippet</th>
       <!-- <td><?php echo htmlspecialchars($snippet, ENT_NOQUOTES, 'utf-8'); ?></td>  -->
      <td><?php echo $snippet; ?></td>
    </tr>
        </table>
      </li>
<?php
  }
?>
    </ol>
<?php
}
?>
  </body>
</html>
