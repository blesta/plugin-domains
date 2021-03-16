<?php
$lang['AdminDomains.!success.registrar_upgraded'] = 'The module was successfully upgraded.';
$lang['AdminDomains.!success.registrar_installed'] = 'The module was successfully installed.';
$lang['AdminDomains.!success.registrar_uninstalled'] = 'The module was successfully uninstalled.';
$lang['AdminDomains.!success.configuration_updated'] = 'The Domain Manager configuration has been updated!';
$lang['AdminDomains.!success.change_auto_renewal'] = 'The Domain auto renewal has been updated!';

$lang['AdminDomains.index.page_title'] = 'Domain Manager - AdminDomains';

$lang['AdminDomains.index.boxtitle'] = 'index';
$lang['AdminDomains.index.submit'] = 'index';


$lang['AdminDomains.browse.boxtitle_browse'] = 'Domain Manager - Browse Domains';
$lang['AdminDomains.browse.heading_domain'] = 'Domain';
$lang['AdminDomains.browse.heading_client'] = 'Client';
$lang['AdminDomains.browse.heading_registrar'] = 'Registrar';
$lang['AdminDomains.browse.heading_price'] = 'Price';
$lang['AdminDomains.browse.heading_registration'] = 'Registration Date';
$lang['AdminDomains.browse.heading_expiration'] = 'Expiration Date';
$lang['AdminDomains.browse.heading_renew'] = 'Auto Renewal';
$lang['AdminDomains.browse.heading_options'] = 'Options';
$lang['AdminDomains.browse.option_delete'] = 'Delete';
$lang['AdminDomains.browse.option_manage'] = 'Manage';
$lang['AdminDomains.browse.confirm_delete'] = 'Are you sure you want to delete this domain service?';
$lang['AdminDomains.browse.text_none'] = 'There are no registered domains.';
$lang['AdminDomains.browse.text_yes'] = 'Yes';
$lang['AdminDomains.browse.text_no'] = 'No';
$lang['AdminDomains.browse.on'] = 'On';
$lang['AdminDomains.browse.off'] = 'Off';

$lang['AdminDomains.browse.category_active'] = 'Active';
$lang['AdminDomains.browse.category_canceled'] = 'Canceled';
$lang['AdminDomains.browse.category_suspended'] = 'Suspended';
$lang['AdminDomains.browse.category_pending'] = 'Pending';
$lang['AdminDomains.browse.category_in_review'] = 'In Review';
$lang['AdminDomains.browse.category_scheduled_cancellation'] = 'Scheduled';
$lang['AdminDomains.browse.field_actionsubmit'] = 'Submit';


$lang['AdminDomains.getdomainactions.change_auto_renewal'] = 'Change Auto Renewal';


$lang['AdminDomains.registrars.boxtitle_registrars'] = 'Domain Manager - Registrars';
$lang['AdminDomains.registrars.text_author'] = 'Author:';
$lang['AdminDomains.registrars.text_author_url'] = 'Author URL';
$lang['AdminDomains.registrars.text_version'] = '(ver %1$s)'; // %1$s is the module's version number
$lang['AdminDomains.registrars.btn_install'] = 'Install';
$lang['AdminDomains.registrars.btn_uninstall'] = 'Uninstall';
$lang['AdminDomains.registrars.btn_manage'] = 'Manage';
$lang['AdminDomains.registrars.btn_upgrade'] = 'Upgrade';
$lang['AdminDomains.registrars.text_none'] = 'There are no available registrars.';

$lang['AdminDomains.registrars.confirm_uninstall'] = 'Are you sure you want to uninstall this registrar?';


$lang['AdminDomains.configuration.boxtitle'] = 'Domain Manager - Configuration';
$lang['AdminDomains.configuration.heading_tld'] = 'TLD Settings';
$lang['AdminDomains.configuration.heading_notice'] = 'Notice Settings';

$lang['AdminDomains.configuration.field_package_group'] = 'TLD Package Group';
$lang['AdminDomains.configuration.field_dns_management_option_group'] = 'DNS Management Option Group';
$lang['AdminDomains.configuration.field_email_forwarding_option_group'] = 'Email Forwarding Option Group';
$lang['AdminDomains.configuration.field_id_protection_option_group'] = 'ID Protection Option Group';
$lang['AdminDomains.configuration.field_first_reminder_days_before'] = '1st Renewal Reminder Days Before';
$lang['AdminDomains.configuration.field_second_reminder_days_before'] = '2nd Renewal Reminder Days Before';
$lang['AdminDomains.configuration.field_expiration_notice_days_after'] = 'Expiration Notice Days After';
$lang['AdminDomains.configuration.field_spotlight_tlds'] = 'Spotlight TLDs';
$lang['AdminDomains.configuration.field_submit'] = 'Update Configuration';

$lang['AdminDomains.configuration.link_template'] = 'Email Template';

$lang['AdminDomains.configuration.tooltip_domain_manager_package_group'] = 'The package group to which all TLD price management packages will be assigned.';
$lang['AdminDomains.configuration.tooltip_dns_management_option_group'] = 'The configurable option group used to control whether a domain will have DNS management services.';
$lang['AdminDomains.configuration.tooltip_email_forwarding_option_group'] = 'The configurable option group used to control whether a domain will have email forwarding services.';
$lang['AdminDomains.configuration.tooltip_id_protection_option_group'] = 'The configurable option group used to control whether a domain will have ID protection services.';
$lang['AdminDomains.configuration.tooltip_first_reminder_days_before'] = 'Select the number of days before a domain expires to send the first renewal email (26-35 as per ICANN specs). Use the Email Template link to modify/disable this email.';
$lang['AdminDomains.configuration.tooltip_second_reminder_days_before'] = 'Select the number of days before a domain expires to send the second renewal email (4-10 per ICANN specs). Use the Email Template link to modify/disable this email.';
$lang['AdminDomains.configuration.tooltip_expiration_notice_days_after'] = 'Select the number of days after a domain expires to send the expiration notice email (1-5 per ICANN specs). Use the Email Template link to modify/disable this email.';
$lang['AdminDomains.configuration.tooltip_spotlight_tlds'] = 'TLDs that we may highlight on order forms through the Order Plugin.  This feature is not yet supported';


$lang['AdminDomains.whois.boxtitle_whois'] = 'Domain Manager - Whois Domain Lookup';
$lang['AdminDomains.whois.title_row'] = 'Domain Lookup';
$lang['AdminDomains.whois.available'] = 'Domain Available';
$lang['AdminDomains.whois.unavailable'] = 'Domain Unavailable';
$lang['AdminDomains.whois.field_domain'] = 'Domain';
$lang['AdminDomains.whois.field_submit'] = 'Lookup';


$lang['AdminDomains.getDays.never'] = 'Never';
$lang['AdminDomains.getDays.text_day'] = '%1$s Day'; // %1$s is the number of days
$lang['AdminDomains.getDays.text_days'] = '%1$s Days'; // %1$s is the number of days
