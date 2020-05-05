#!/usr/bin/env bash
{

# Orders
mysqldump -u stim -p1234 hyber_test wp_posts -w "ID > 10900 AND post_type='shop_order'" --no-create-info --single-transaction & 

# Order meta
mysqldump -u stim -p1234 hyber_test wp_postmeta -w "post_id IN ( select ID from wp_posts where post_type='shop_order' and ID > 10900 )" --no-create-info --single-transaction &

# Order items
mysqldump -u stim -p1234 hyber_test wp_woocommerce_order_items -w "order_id IN ( select ID from wp_posts where post_type='shop_order' and ID > 10900 )" --no-create-info --single-transaction &

# Order itemmeta
mysqldump -u stim -p1234 hyber_test wp_woocommerce_order_itemmeta -w "order_item_id IN ( select order_item_id from wp_woocommerce_order_items where order_id IN ( select ID from wp_posts where post_type='shop_order' and ID > 10900 ) )" --no-create-info --single-transaction

# Customers
mysqldump -u stim -p1234 hyber_test wp_users -w "ID IN ( select meta_value from wp_postmeta where meta_key = '_customer_user' AND post_id IN ( select ID from wp_posts WHERE post_type='shop_order' and ID > 10900 ) )" --no-create-info --single-transaction &

# Customer meta
mysqldump -u stim -p1234 hyber_test wp_usermeta -w "user_id IN ( select meta_value from wp_postmeta where meta_key = '_customer_user' AND post_id IN ( select ID from wp_posts WHERE post_type='shop_order' and ID > 10900 ) )" --no-create-info --single-transaction

} | sed -e "s/([0-9]*,/(NULL,/gi" | gzip > sql_posts.sql.gz
