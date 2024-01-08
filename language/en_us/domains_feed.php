<?php
/**
 * en_us language for the Domain Manager data feed
 */
$lang['DomainsFeed.name'] = 'Domains';
$lang['DomainsFeed.description'] = 'Returns an HTML table containing the pricing for all TLDs or the number of registered domains.';

$lang['DomainsFeed.getOptionFields.title_row_example_code'] = 'Example Code';
$lang['DomainsFeed.getOptionFields.example_code_pricing'] = 'Show a table containing the pricing for all the TLDs in a given currency:';
$lang['DomainsFeed.getOptionFields.example_code_count'] = 'Show the number of registered domains:';
$lang['DomainsFeed.getOptionFields.header_name'] = 'Name';
$lang['DomainsFeed.getOptionFields.header_description'] = 'Description';
$lang['DomainsFeed.getOptionFields.params'] = 'Parameters';
$lang['DomainsFeed.getOptionFields.param_currency_description'] = 'The 3-character currency code for which to fetch pricing.';
$lang['DomainsFeed.getOptionFields.param_style_description'] = 'The style of table to fetch: html or bootstrap.';
$lang['DomainsFeed.getOptionFields.param_term_description'] = 'A comma-separated list of year terms to include in the pricing table.';
$lang['DomainsFeed.getOptionFields.param_status_description'] = 'The status for which to filter domains: active, canceled or pending.';
$lang['DomainsFeed.getOptionFields.param_tlds_description'] = 'A comma-separated list of TLDS (excluding initial dot), to filter domains.';


$lang['DomainsFeed.table.heading_tlds'] = 'TLDs';
$lang['DomainsFeed.table.heading_year'] = '%1$s Year'; // %1$s is the number of years
$lang['DomainsFeed.table.heading_years'] = '%1$s Years'; // %1$s is the number of years
$lang['DomainsFeed.table.register'] = 'Register';
$lang['DomainsFeed.table.transfer'] = 'Transfer';
$lang['DomainsFeed.table.renew'] = 'Renew';
$lang['DomainsFeed.table.not_available'] = 'Not available';

$lang['DomainsFeed.!error.invalid_endpoint'] = 'The requested endpoint is not valid or does not exist.';
$lang['DomainsFeed.!error.invalid_style'] = 'The requested style is not valid.';
