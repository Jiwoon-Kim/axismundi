<?php
/**
 * Seed the Axismundi VQA Widgets page — give the data-driven widget-family blocks
 * (latest-posts / latest-comments / archives / calendar / categories / tag-cloud)
 * real content so the specimens render meaningfully, and (re)build the VQA Widgets
 * page itself from the active theme's patterns/vqa-widgets.php source.
 *
 * Idempotent: update-or-create by deterministic slug (`vqa-widgets`, `vqa-demo-post-N`).
 * The page body is regenerated from the CURRENT pattern file every run, so after
 * editing patterns/vqa-widgets.php just re-run this to resync the stored page.
 *
 * Run (from repo root):
 *   npx wp-env run cli wp eval-file - < products/distributables/themes/axismundi/scripts/seed-vqa-widgets.php
 *
 * @package Axismundi
 */

// demo content for data-driven widgets (idempotent)
$cat = term_exists('News','category') ?: wp_insert_term('News','category');
$cat_id = is_array($cat)?$cat['term_id']:$cat;
$tag = term_exists('demo','post_tag') ?: wp_insert_term('demo','post_tag');
for ($i=1;$i<=3;$i++){
  $slug="vqa-demo-post-$i";
  if (!get_page_by_path($slug,OBJECT,'post')){
    $pid=wp_insert_post(array('post_title'=>"VQA demo post $i",'post_name'=>$slug,'post_status'=>'publish','post_type'=>'post','post_content'=>"Demo post $i body for widget VQA (latest-posts / archives / calendar).",'post_date'=>date('Y-m-d H:i:s', strtotime("-$i days"))));
    wp_set_post_categories($pid,array((int)$cat_id));
    wp_set_post_tags($pid,array('demo'));
    if ($i===1){ wp_insert_comment(array('comment_post_ID'=>$pid,'comment_author'=>'Demo Commenter','comment_author_email'=>'demo@example.com','comment_content'=>'A demo comment for the latest-comments widget.','comment_approved'=>1)); }
  }
}
// seed the widgets page from the active theme's pattern source
$file = get_theme_file_path('patterns/vqa-widgets.php');
$content = preg_replace('/^<\?php.*?\?>\s*/s','',file_get_contents($file));
$ex=get_page_by_path('vqa-widgets');
$args=array('post_title'=>'VQA Widgets','post_name'=>'vqa-widgets','post_status'=>'publish','post_type'=>'page','post_content'=>$content);
if($ex){$args['ID']=$ex->ID;$id=wp_update_post($args);}else{$id=wp_insert_post($args);}
echo "page id: $id url: ".get_permalink($id)."\n";
echo "posts: ".wp_count_posts()->publish." comments: ".get_comments_number_text()."\n";
