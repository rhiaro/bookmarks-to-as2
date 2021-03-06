<?
// CONFIG
$options = array(
  "base" => "https://rhiaro.co.uk/" // For URIs for new Add Activities
 ,"bookmarks" => "https://rhiaro.co.uk/bookmarks/" // URI of bookmarks collection
 ,"bm_name" => "Bookmarks" // Name of bookmarks collection, for summary
 ,"name" => "Amy" // For summary: "{$name} added {$bookmark_name} to {$bm_name}"
 ,"actor" => "https://rhiaro.co.uk/#me"
);
$file = "bookmarks-2017-01-10.json";

function parse_bookmarks($container){
  // Grab the relevant things from the firefox JSON
  $items = array();
  foreach($container as $item){
    if($item["type"] == "text/x-moz-place" && substr($item["uri"], 0, 4) == "http"){
      $add = array();
      $date = new DateTime();
      $date->setTimestamp($item["dateAdded"] / 1000000);
      $bm = array("id" => $item["uri"], "published" => $date->format(DATE_ATOM));
      if(isset($item["title"])){
        $bm["name"] = $item["title"];
      }
      if(isset($item["annos"])){
        // This is simplest possible based on my own bookmarks data, where I do not actually annotate or organise stuff using ff bookmarks.
        // From my data, booksmarks either have 0 or 1 'annos' and it's a summary of the contents of the bookmark.
        // If you have different data in your annotations, you might want to modify this. I don't know how it works.
        $bm["summary"] = $item["annos"][0]["value"];
      }
      $items[] = $bm;
    }elseif(isset($item["children"])){
      $items = array_merge_recursive($items, parse_bookmarks($item["children"]));
    }
  }
  return $items;
}

function make_as2($options, $file){
  // This returns an array of as:Add activities as json blobs.
  $adds = array();
  $json = json_decode(file_get_contents($file), true);
  $bms = parse_bookmarks($json["children"]);
  foreach($bms as $bm){
    
    $d = new DateTime($bm["published"]);
    $y = $d->format("Y");
    $m = $d->format("m");
    $uri = $options["base"].$y."/".$m."/".uniqid();

    if(isset($bm["name"])){
      $obj = $bm["name"];
    }else{
      $obj = $bm["id"];
    }
    $summary = $options["name"]." added '".$obj."' to ".$options["bm_name"];
    
    $add = array("@context" => "https://www.w3.org/ns/activitystreams#"
                ,"type" => "Add"
                ,"id" => $uri
                ,"published" => $bm["published"]
                ,"summary" => $summary
                ,"object" => array(
                     "id" => $bm["id"]
                    ,"type" => "Article"
                    ,"name" => $obj
                  )
                ,"target" => $options["bookmarks"]
                ,"actor" => $options["actor"]
      );

    $adds[] = stripslashes(json_encode($add));
  }

  return $adds;
  
}

$bms = make_as2($options, $file);

if(isset($_POST['submit'])){
  $keep = array();
  foreach($bms as $i => $json){
    $bm = json_decode($json, true);
    if(in_array($bm['object']['id'], $_POST['bm'])){
      $keep[] = $bm;
    }
  }
  header("Content-Type: application/json");
  echo json_encode($keep);
  die();
}
?>
<!doctype html>
<html>
  <head><title>Bookmarks as AS2</title></head>
  <body>
    <h1>Bookmarks</h1>
    <p>Choose bookmarks to keep</p>
    <form method="post">
      <?foreach($bms as $json):?>
        <? $bm = json_decode($json, true); ?>
        <p><strong><input type="checkbox" name="bm[]" value="<?=$bm['object']['id']?>" id="<?=$bm['object']['id']?>" /> <label for="<?=$bm['object']['id']?>"><?=$bm['id']?></label></strong></p>
        <p><a href="<?=$bm['object']['id']?>"><?=$bm['object']['name']?></a> (<?=$bm['published']?>)</p>
        <?if(isset($bm['object']['summary'])):?>
          <p><?=$bm['object']['summary']?></p>
        <?endif?>
      <?endforeach?>
      <p><input type="submit" name="submit" value="Make it so" /></p>
    </form>
  </body>
</html>