<?php
/**
 * Created by PhpStorm.
 * User: stim
 * Date: 4/14/20
 * Time: 12:20 PM
 */

/**
 * @No_Translations for now
 */

namespace Wetail\Woo\Migration;

?>
<p class="clear"></p>

		<div class="wrap wtwoo-migrate-wrapper">

			<hr class="wp-header-end" />

			<h3 class="inline-header">WooCommerce Site-to-Site migration</h3>

			<p class="description">Simple way to transfer any Woo data from site to site.
				<a href="#" onclick="document.getElementById('contextual-help-link').click()">More info</a>
			</p>

			<table class="form-table">

				<tbody>

				<tr valign="top">
					<th class="titledesc" scope="row">
						<label for="url">Destination URL</label>
					</th>
					<td class="forminp forminp-text">
						<p>
							<input id="url" autocomplete="false" aria-autocomplete="none"
							       placeholder="https://"
							       type="text"
                                   name="url-edit"
							       value="<?php echo options('url') ?>"
                                   onchange="if( this.value ) document.getElementById('url-value').value=this.value"
                            />

                            <input type="hidden"
                                   id="url-value"
                                   name="url"
                                   value="<?php echo options('url') ?>" />
						</p>
						<p>
							<button class="button button-primary" id="wtwm-check-connection">Connect</button>
						</p>
						<hr />
					</td>
				</tr>

				<tr valign="top">
					<th class="titledesc" scope="row">
						<label for="url">Data for migration</label>
					</th>
					<td class="forminp forminp-text">

						<div class="migration-data">
							<p>
								<label>
									<input class="pre-settings" type="checkbox" value="yes" name="pre-settings"
										<?php echo ( 'yes' === ( options( 'pre-settings' ) ?? '' ) ? 'checked' : '' )?>
									/>
									Presync all Woo settings and options
								</label>
							</p>
                            <p class="description">
                                This is important if we want tax/shipping/payment gateways <br/>
                                and product attributes to be same and ok after migration
                            </p>
							<br/>
							<p>
								<label>
									Data type:<br/>
									<select id="migrate-data-type" name="data-type">
										<?php $dt = options( 'data-type' ) ?>
										<option value="orders"
											<?php echo ( 'orders' === $dt ? 'selected' : '' ) ?> >
											Orders
										</option>
										<option value="products"
											<?php echo ( 'products' === $dt ? 'selected' : '' ) ?> >
											Products
										</option>
										<option value="coupons"
											<?php echo ( 'coupons' === $dt ? 'selected' : '' ) ?> >
											Coupons
										</option>
										<option value="categories"
											<?php echo ( 'categories' === $dt ? 'selected' : '' ) ?> >
											Categories
										</option>
										<option value="customers"
											<?php echo ( 'customers' === $dt ? 'selected' : '' ) ?> >
											Customers
										</option>
									</select>
								</label>
							</p>
						</div>

						<hr/>

						<label>Relative data and range</label>

						<div class="migrate-rel orders">
							<?php $o = options( 'orders' ) ?>
							<p>
								<label>
									<input class="orders-products" type="checkbox" value="yes" name="orders[products]"
										<?php echo ( 'yes' === $o['products'] ? 'checked' : '' )?> />
									Include products data (+attributes, categories, tags, media)<br/>
                                    <i class="wtwm-warn">
                                        Enabling this may make syncing process really heavy, <br/>
                                        make sure there is enough disk space for this operation
                                    </i>
								</label>
							</p>
							<p>
								<label>
									<input class="orders-products" type="checkbox" value="yes" name="orders[coupons]"
										<?php echo ( 'yes' === $o['coupons'] ? 'checked' : '' )?> />
									Include coupons data
								</label>
							</p>
							<p>
								<label>
									<input class="orders-customers" type="checkbox" value="yes" name="orders[customers]"
										<?php echo ( 'yes' === $o['customers'] ? 'checked' : '' )?> />
									Include customers data
								</label>
							</p>
							<p>
								<label>
									Range of IDs:<br/>
									<input class="orders-range data-range"
									       type="text"
									       value="<?php echo $o['range'] ?>"
									       placeholder="123-321"
									       name="orders[range]"/>
								</label>
							</p>
						</div>

						<div class="migrate-rel products">
							<?php $o = options( 'products' ) ?>
                            <p>
                                <label>
                                    <input class="products-media" type="checkbox" value="yes" name="products[media]"
										<?php echo ( 'yes' === $o['media'] ? 'checked' : '' )?> />
                                    Include media<br/>
                                    <i class="wtwm-warn">Be aware about sufficient disk space for this operation!</i>
                                </label>
                            </p>
							<p>
								<label>
									Range of IDs:<br/>
									<input class="products-range data-range"
									       type="text"
									       value="<?php echo $o['range'] ?>"
									       placeholder="123-321"
									       name="products[range]"/>
								</label>
							</p>
						</div>

						<div class="migrate-rel coupons">
							<?php $o = options( 'coupons' ) ?>
							<p>
								<label>
									Range of IDs:<br/>
									<input class="coupons-range data-range"
									       type="text"
									       value="<?php echo $o['range'] ?>"
									       placeholder="123-321"
									       name="coupons[range]"/>
								</label>
							</p>
						</div>

						<div class="migrate-rel categories">
							<?php $o = options( 'categories' ) ?>
							<p>
								<label>
									Range of IDs:<br/>
									<input class="categories-range data-range"
									       type="text"
									       value="<?php echo $o['range'] ?>"
									       placeholder="123-321"
									       name="categories[range]"/>
								</label>
							</p>
						</div>

						<div class="migrate-rel customers">
							<?php $o = options( 'customers' ) ?>
							<p>
								<label>
									Range of IDs:<br/>
									<input class="customers-range data-range"
									       type="text"
									       value="<?php echo $o['range'] ?>"
									       placeholder="123-321"
									       name="customers[range]"/>
								</label><br/>
                                <i>Do note: this may be used for any WP users</i>
							</p>
						</div>

						<hr />

					</td>
				</tr>

				</tbody>

				<tfoot>
				<tr valign="top">
					<th class="titledesc" scope="row">
						<label>Progress</label>
					</th>
					<td class="forminp forminp-text">
						<div id="progress">
							<label>Checking</label>
							<span></span>
						</div>

                        <div id="verify-data">
                            <h4>Verify data before sending</h4>
                            <p>There is an archive created in your uploads folder</p>
                            <br/>
                            <div class="wtwm-warn" id="data-to-verify"><?php echo wp_get_upload_dir()['path'] ?></div>
                            <br/>
                            <p class="description">Please, verify everything.</p>
                            <br/>
                            <button id="wtwm-transfer" class="button button-primary">Transfer</button>
                            <button id="wtwm-cancel" class="button button-secondary">Cancel</button>
                        </div>

                        <div id="launch-controls">
                            <button id="wtwm-launch" class="button button-primary disabled" disabled
                                    title="Not connected...">Launch</button>

                            <button id="wtwm-stop" class="button button-secondary" disabled>Stop</button>

                            <p class="description">
                                Launch consists of 3 steps:<br/>
                                1. Automatically collect and verify data<br/>
                                2. Review and confirm<br/>
                                3. Automatically zip, send, verify, unzip, insert data<br/>
                                <br/>
                                <i class="wtwm-warn">
                                    Please, make sure you made a backup on remote host <b>before</b> launching!
                                </i>
                            </p>
                        </div>
					</td>
				</tr>
				</tfoot>

			</table>

			<hr/>

			<input type="hidden" name="wtwm-save-options" value="yes" />

		</div>