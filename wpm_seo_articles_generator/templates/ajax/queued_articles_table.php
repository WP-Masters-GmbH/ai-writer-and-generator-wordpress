<table class="wpm-status-table five-column" cellpadding="0" cellspacing="0">
    <tbody>
	<?php if(!empty($queued_articles)) {
		foreach($queued_articles as $article) {
			$category = get_the_category_by_ID($article->category);
			?>
            <tr>
                <td><?php echo esc_html($article->article_name); ?></td>
                <td><?php
					if(get_option('date_format')) {
						echo esc_html(date(get_option('date_format'), strtotime($article->timestamp)));
					} else {
						echo esc_html(date('Y-m-d H:i:s', strtotime($article->timestamp)));
					}
					?></td>
                <td><?php if(is_string($category)) { echo esc_html($category); } else { echo esc_html('Not found'); } ?></td>
                <td><?php if($article->article_content != '') { ?> <a class="wpm-button-black" href="<?php if($article->post_id == 0) { ?>https://ai.wp-masters.com?view_article=<?php echo esc_attr($article->article_name); ?>&activation_key=<?php if(isset($settings['activation_key'])) { echo esc_attr($settings['activation_key']); } else { echo esc_attr('FREE'); } ?><?php } else { echo esc_attr(get_permalink($article->post_id)); } ?>" target="_blank">View Article</a>
	                <?php if($article->post_id == 0) { ?><a class="wpm-button-black wpm-click-remove" href="<?php echo esc_attr(get_home_url()."?import_ai_post={$article->id}"); ?>" target="_blank">Import Post</a><?php } ?> <?php } elseif($article->errors != '') { ?><a class="wpm-button-black wpm-red-btn modal-view-content" href="#modal-content" rel="modal:open" data-title="<?php echo esc_attr($article->article_name); ?>" data-article-content="<?php echo esc_html(implode(', ', $article->errors)); ?>">View Errors</a><?php } else { echo esc_html("In Process"); } ?></td>
                <td><?php if($article->date_posted != '' && strtotime($article->date_posted) > 0) {
						if(get_option('date_format')) {
							echo esc_html(date(get_option('date_format'), strtotime($article->date_posted)));
						} else {
							echo esc_html(date('Y-m-d H:i:s', strtotime($article->date_posted)));
						}
					} else {
						echo esc_html('Not Posted');
					} ?></td>
            </tr>
		<?php }} else { ?>
        <tr>
            <td>No articles is sent to Assistant</td>
        </tr>
	<?php } ?>
    </tbody>
</table>