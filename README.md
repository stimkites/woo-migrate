# WooCommerce Site-to-Site Migration 
Simple way to migrate your Woo data from one WP instance into another.

### Usage

WooCommerce->Settings->Migrate tab:

1.  Define destination URL
2.  Press check connection (this plugin must be installed on destination instance as well)
3.  Define data type (products/orders/categories ...)
4.  Define relative data (optional)
5.  Define data range (leave empty for all data)
6.  Launch

Do note: this is a potentially risky operation due to Woo and WP being changed (updated) almost every day,
keep your head up and eyes open if both - source and destination instances - have same environments.

It is highly recommended to perform data backup on destination before any actions done. 

### Version log
- 0.0.1
    - Initial version
