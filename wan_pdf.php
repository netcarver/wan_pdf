<?php
// This is a PLUGIN TEMPLATE.
// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Uncomment and edit this line to override:
$plugin['name'] = 'wan_pdf';
$plugin['version'] = '0.30';
$plugin['author'] = 'Martin Wannert';
$plugin['author_uri'] = 'http://wannert.net/';
$plugin['description'] = 'Generates pdf-versions of an article';

// Plugin types:
// 0 = regular plugin; loaded on the public web side only
// 1 = admin plugin; loaded on both the public and admin side
// 2 = library; loaded only when include_plugin() or require_plugin() is called
$plugin['type'] = '0';

@include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

// ----------------------------------------------------


function value_entity_decode($html)
{
//replace each value entity by its respective char
  preg_match_all('|&#(.*?);|',$html,$temparray);
  foreach($temparray[1] as $val) $html = str_replace("&#".$val.";",utf8_encode(chr($val)),$html);
  return $html;
}



  function wan_pdf($atts) {
    global $thisarticle, $sitename, $permlink_mode, $prefs, $txpcfg;;

    extract(lAtts(array(
			'class'  => '',
			'pdf_css_class' => 'article_pdf',
			'name' => 'This article as a pdf',
			'file_category' => 'article_pdf',
			'show_excerpt' => 'y',
			'show_body' => 'y',
			'image' => '',
			'debug' => 'n'
		),$atts));

    $pdf_exists = false;
    $pdf_up_to_date = false;
    $article_id = $thisarticle['thisid'];
    $title = $thisarticle['title'];
    $body = '';
    $excerpt = '';
    $tempdir = $prefs['tempdir'];
    $filedir = $prefs['file_base_path'];

    if ($show_body == 'y') {
      if ($thisarticle['body'] != "") {
        //$body = "<div class=\"article_body\">".$thisarticle['body']."</div>";
        $body = $thisarticle['body'];
      }
    }
    if ($show_excerpt == 'y') {
      if ($thisarticle['excerpt'] != "") {
        //$excerpt = "<div class=\"excerpt\">".$thisarticle['excerpt']."</div>";
        $excerpt = $thisarticle['excerpt'];
      }
    }

    // Read CSS-file for pdf-output
    $css = safe_field('css','txp_css',"name='".doSlash($pdf_css_class)."'");

    // Calculate hash-value for article
    $article_hash = md5($css.$title.$excerpt.$body);

    // Check if pdf already exists for this article
    $pattern = $article_id."_%";
    $rs = safe_row("id, description, filename", 'txp_file', "category = '$file_category' AND description LIKE '$pattern'");

    if (count($rs) > 0) {
      $pdf_exists = true;
      $file_id = $rs['id'];
      $pdf_filename = $rs['filename'];
      // pdf exists, so check if it's up to date
      $description = explode("_", $rs['description']);

      if ($description[1] == $article_hash) {
        $pdf_up_to_date = true;
      }
    }

    // Delete old PDF
    if (file_exists($filedir."/".$title.".pdf")) {
      unlink($filedir."/".$title.".pdf");
      $pdf_up_to_date = false;
    }

    // if pdf has to be generated (or re-generated)
    if (!$pdf_exists OR !$pdf_up_to_date) {

      // generate identifier
      $identifier = $article_id."_".$article_hash;

      $pdf_filename = $article_id."_".$thisarticle['url_title'].".pdf";
      $pdf_filename = str_replace("'", "", $pdf_filename);

      $x_title = utf8_decode($title);

      // check if textile is on or off
      if (strcmp("<p>", substr($excerpt, 0, 3)) != 0) {
        $excerpt = "<p>".$excerpt."</p>";
        //$x_title = value_entity_decode($title);
      }

      if (strcmp("<p>", substr($body, 0, 3)) != 0) {
        $body = "<p>".$body."</p>";
        $x_title = value_entity_decode($title);
      } else {
		    $body = value_entity_decode($body);
	    }

      // change image-urls to http://...
      $excerpt = str_replace('<img src="/textpattern', '<img src="http://'.$prefs['siteurl'], $excerpt);
      $body = str_replace('<img src="/textpattern', '<img src="http://'.$prefs['siteurl'], $body);

      // generate HTML first
      if (is_writable($tempdir."/")) {
        $file = fopen($tempdir."/".$identifier.".html", "w");
        $xhtml_header = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\"
            \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
            <html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\">
            <head>
            <meta http-equiv=\"Content-Type\" content=\"text/HTML;\"  />
            <title>".$title."</title>
            </head>
            <body>";
        //fwrite($file, utf8_decode($xhtml_header."<h1>".$thisarticle['title']."</h1>\n".$excerpt.$body."</body></html>"));
        fwrite($file, $xhtml_header."<h1>".$title."</h1>\n".$excerpt.$body."</body></html>");
        fclose($file);

        // write CSS to file
		  if ($css) {
		      $cssfile = fopen($tempdir."/csstemp.css", "w");
          fwrite($cssfile, base64_decode($css));
          fclose($cssfile);
        }

        // Define some variables for PDF
        define('X_PATH', $txpcfg['txpath'].'/lib/xhtml2pdf');
        //define('X_NAME', utf8_decode($sitename));
        //define('X_TITLE', html_entity_decode(utf8_decode($thisarticle['title'])));
        //$x_name = utf8_decode($sitename);
        //$x_title = html_entity_decode(utf8_decode($title));
        $x_name = $sitename;
        //

        include_once(X_PATH.'/classes/x2fpdf.php');

        // Create new xhtml2pdf-object
        $xpdf = new xhtml2pdf ($tempdir."/".$identifier.".html", $tempdir."/csstemp.css", $config);
        $xpdf->SetTitle(utf8_decode($title));
        $xpdf->SetAuthor(utf8_decode($thisarticle['authorid']));
        $xpdf->SetCreator('XHTML2PDF v0.2.5');
        $xpdf->SetSubject(utf8_decode($title));
        $xpdf->SetKeywords(utf8_decode($thisarticle['keywords']));

        // output pdf
        $xpdf->output($filedir."/".$pdf_filename, 'F');

        // remove HTML-file, remove CSS-file
        if ($debug != 'y') {
          unlink($tempdir."/".$identifier.".html");
          unlink($tempdir."/csstemp.css");
        }
      }


      if (!$pdf_exists) {
        // Add pdf to textpattern-db
        $file_id = safe_insert("txp_file",
			       "filename = '$pdf_filename',
			       category = '$file_category',
			       permissions = '',
			       description = '$identifier'
		    ");

      } else if (!$pdf_up_to_date) {
        // Update textpattern-db
        safe_update("txp_file", "description = '$identifier', filename = '$pdf_filename'", "id = '$file_id'");

      }
    }


		// Generate Link to PDF
		if ($class != '') {
      $class = " class=\"".$class."\"";
    }

    if ($image != '') {
      $name = image(array('id' => $image));
    }

		if ($permlink_mode == 'messy') {
			$url = '<a href="http://'.$prefs['siteurl'].'/index.php?s=file_download&id='.$file_id.'" title="download file '.$pdf_filename.'"'.$class.'>'.$name.'</a>';
		} else {
			$url = '<a href="http://'.$prefs['siteurl'].'/'.gTxt('file_download').'/'.$file_id.'" title="download file '.$pdf_filename.'"'.$class.'>'.$name.'</a>';
		}
    return $url;
  }
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
<h1>Textpatternplugin for <span class="caps">PDF </span>output of your articles</h1>

<p>The plugin <em class="mono">wan_pdf</em> generates <span class="caps">PDF</span>-versions of your articles. The output is a link to the <span class="caps">PDF</span>-file. The <span class="caps">PDF</span>s are not generated every time the article is called, but only when the title or the body has changed. For this plugin I used <a href="http://xhtml2pdf.mandragor.org/fr/index">xhtml2pdf</a> which is based on <a href="http://fpdf.org">fpdf</a>. All needed files can be found at the end of this article.</p>

<p><strong>Attention: If you update from a version <= 0.2 please download xhtml2pdf.zip once again and delete all PDFs due to changes in the name scheme.</strong></p>

<h2>Installation</h2>

<p>The installation of this plugin is a little bit more complicated than the installation of other plugins. So here are the instructions:</p>


<ol>
<li>download the plugin and install and activate in the admin-panel</li>
<li>download the file <em class="file">xhtml2pdf.zip</em> and unzip it to your local harddrive. After that you copy the folder <em class="mono">xhtml2pdf</em> (and not only the content of this folder) into the folder /textpattern/lib/ via ftp.</li>
<li>create a new style with the name <em class="mono">article_pdf</em> and copy &#38; paste the content of the file <em class="file">article_pdf.css</em> into it</li>
<li>create a new file category with the name <em class="mono">article_pdf</em> </li>
</ol>



<h2>Usage</h2>

<p>Just put the tag <code>&lt;txp:wan_pdf /&gt;</code> somewhere in you article form and there will be placed a link to the <span class="caps">PDF</span>-version of this article. The following optional arguments are possible:</p>


<ul>
<li><em class="mono">class:</em> <span class="caps">CSS</span>-class for the link (default: class="")</li>
<li><em class="mono">name:</em> Link-text (default: name="This article as a pdf")</li>
<li><em>show_body:</em> show article body in PDF ("y" or "n", default: show_body="y")</li>
<li><em>show_excerpt:</em> show excerpt in PDF ("y" or"n", default: show_excerpt="y")</li>
<li><em>image:</em> ID of an image that is linked instead of the text (like you can see on this page). If the argument is not set, the default text is used (default: image="")</li>
<li><em class="mono">file_category:</em> file categrory the <span class="caps">PDF </span>will belong to (default: file_category="article_pdf")</li>
<li><em class="mono">pdf_css_class:</em> <span class="caps">CSS</span>-class for <span class="caps">PDF</span>-creation (default: pdf_css_class="article_pdf")</li>
<li><em>debug:</em> only for testing. The XHTML- and CSS-file are not deleted (default: debug="n")</li>
</ul>



<p>The last two arguments are only for advanced users who know what they do.</p>

<h2>How it works</h2>

<p>With every call of an article the plugin checks, if there exists a <span class="caps">PDF </span>with the current version of an article. If this is not the case, first a xhtml-document with this article is created. With the help of xhtml2pdf and the stylesheet article_pdf this xhtml-document is converted into a <span class="caps">PDF </span>document. It will be saved in the folder files/ and put into the database at file category "article_pdf".</p>

<p>If the file is already existing, the only thing the plugin does ist output a link to it.</p>

<h2>Customization (for advanced users)</h2>

<p>Edit the stylesheet <em class="mono">article_pdf</em> . Also a different stylesheet can be used via the optional argument pdf_css_class.</p>

<p>You can edit the header and the footer of the pdf in the file <em class="file">/textpattern/lib/xhtml2pdf/classes/x2fpdf.php</em> in line 377 (functions <em class="mono">header()</em> and <em class="mono">footer()</em> ).</p>

<p>At default the <span class="caps">PDF</span>s are saved in the category article_pdf. If you want to save in in another category, you have to create it and use it with category="new_category".</p>
# --- END PLUGIN HELP ---
-->
<?php
}
?>