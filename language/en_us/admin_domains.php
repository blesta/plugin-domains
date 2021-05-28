<?php
$lang['AdminDomains.!success.registrar_upgraded'] = 'The module was successfully upgraded.';
$lang['AdminDomains.!success.registrar_installed'] = 'The module was successfully installed.';
$lang['AdminDomains.!success.registrar_uninstalled'] = 'The module was successfully uninstalled.';
$lang['AdminDomains.!success.configuration_updated'] = 'The Domain Manager configuration has been updated!';
$lang['AdminDomains.!success.tld_disabled'] = 'The TLD was successfully disabled!';
$lang['AdminDomains.!success.tld_enabled'] = 'The TLD was successfully enabled!';
$lang['AdminDomains.!success.tld_added'] = 'The TLD was successfully added!';
$lang['AdminDomains.!success.tld_updated'] = 'The TLD was successfully updated!';
$lang['AdminDomains.!success.change_auto_renewal'] = 'The Domain auto renewal has been updated!';
$lang['AdminDomains.!success.packages_imported'] = 'Domain packages imported successfully!';


$lang['AdminDomains.browse.boxtitle_browse'] = 'Domains';
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
$lang['AdminDomains.browse.text_on'] = 'On';
$lang['AdminDomains.browse.text_off'] = 'Off';

$lang['AdminDomains.browse.category_active'] = 'Active';
$lang['AdminDomains.browse.category_canceled'] = 'Canceled';
$lang['AdminDomains.browse.category_suspended'] = 'Suspended';
$lang['AdminDomains.browse.category_pending'] = 'Pending';
$lang['AdminDomains.browse.category_in_review'] = 'In Review';
$lang['AdminDomains.browse.category_scheduled_cancellation'] = 'Scheduled';
$lang['AdminDomains.browse.field_actionsubmit'] = 'Submit';

$lang['AdminDomains.browse.tooltip_renew'] = 'Auto Renewal for Blesta means that the user will be invoiced automatically and that the domain will be renewed once the invoice is paid.';

$lang['AdminDomains.getdomainactions.change_auto_renewal'] = 'Change Auto Renewal';


$lang['AdminDomains.registrars.boxtitle_registrars'] = 'Registrars';
$lang['AdminDomains.registrars.text_author'] = 'Author:';
$lang['AdminDomains.registrars.text_author_url'] = 'Author URL';
$lang['AdminDomains.registrars.text_version'] = '(ver %1$s)'; // %1$s is the module's version number
$lang['AdminDomains.registrars.btn_install'] = 'Install';
$lang['AdminDomains.registrars.btn_uninstall'] = 'Uninstall';
$lang['AdminDomains.registrars.btn_manage'] = 'Manage';
$lang['AdminDomains.registrars.btn_upgrade'] = 'Upgrade';
$lang['AdminDomains.registrars.text_none'] = 'There are no available registrars.';

$lang['AdminDomains.registrars.confirm_uninstall'] = 'Are you sure you want to uninstall this registrar?';


$lang['AdminDomains.configuration.boxtitle'] = 'Configuration';
$lang['AdminDomains.configuration.tab_general'] = 'General';
$lang['AdminDomains.configuration.tab_notifications'] = 'Notifications';
$lang['AdminDomains.configuration.tab_advanced'] = 'Advanced';
$lang['AdminDomains.configuration.tab_importpackages'] = 'Import Packages';

$lang['AdminDomains.configuration.field_package_group'] = 'TLD Package Group';
$lang['AdminDomains.configuration.field_dns_management_option_group'] = 'DNS Management Option Group';
$lang['AdminDomains.configuration.field_email_forwarding_option_group'] = 'Email Forwarding Option Group';
$lang['AdminDomains.configuration.field_id_protection_option_group'] = 'ID Protection Option Group';
$lang['AdminDomains.configuration.field_epp_code_option_group'] = 'EPP Code Option Group';
$lang['AdminDomains.configuration.field_first_reminder_days_before'] = '1st Renewal Reminder Days Before';
$lang['AdminDomains.configuration.field_second_reminder_days_before'] = '2nd Renewal Reminder Days Before';
$lang['AdminDomains.configuration.field_expiration_notice_days_after'] = 'Expiration Notice Days After';
$lang['AdminDomains.configuration.field_spotlight_tlds'] = 'Spotlight TLDs';
$lang['AdminDomains.configuration.field_submit'] = 'Update Configuration';

$lang['AdminDomains.configuration.link_template'] = 'Edit Email Template';

$lang['AdminDomains.configuration.tooltip_domains_package_group'] = 'The package group to which all TLD price management packages will be assigned.';
$lang['AdminDomains.configuration.tooltip_dns_management_option_group'] = 'The configurable option group used to control whether a domain will have DNS management services.';
$lang['AdminDomains.configuration.tooltip_email_forwarding_option_group'] = 'The configurable option group used to control whether a domain will have email forwarding services.';
$lang['AdminDomains.configuration.tooltip_id_protection_option_group'] = 'The configurable option group used to control whether a domain will have ID protection services.';
$lang['AdminDomains.configuration.tooltip_epp_code_option_group'] = 'The configurable option group used to control whether a domain will have access to the EPP Code.';
$lang['AdminDomains.configuration.tooltip_first_reminder_days_before'] = 'Select the number of days before a domain expires to send the first renewal email (26-35 as per ICANN specs). Use the Email Template link to modify/disable this email.';
$lang['AdminDomains.configuration.tooltip_second_reminder_days_before'] = 'Select the number of days before a domain expires to send the second renewal email (4-10 per ICANN specs). Use the Email Template link to modify/disable this email.';
$lang['AdminDomains.configuration.tooltip_expiration_notice_days_after'] = 'Select the number of days after a domain expires to send the expiration notice email (1-5 per ICANN specs). Use the Email Template link to modify/disable this email.';
$lang['AdminDomains.configuration.tooltip_spotlight_tlds'] = 'TLDs that will be highlighted on order forms through the Order Plugin.';


$lang['AdminDomains.importpackages.boxtitle'] = 'Configuration';
$lang['AdminDomains.importpackages.description'] = 'This tool will search for any packages that use a registrar module and clone the package once for each of the TLDs assigned to it.  If multiple packages are assigned to the same TLD, then the domain manager will clone the first one it encounters and ignore the others.  Only yearly pricings will be cloned.  If a package has no yearly pricings it will be skipped.  Cloned packages will be deactivated if they have no services after import.';

$lang['AdminDomains.importpackages.field_migrate_services'] = 'Migrate Services';
$lang['AdminDomains.importpackages.tooltip_migrate_services'] = 'When checked, any services assigned to the cloned packages will be migrated to the newly created packages.  This will only apply to services with a yearly pricing period.';
$lang['AdminDomains.importpackages.field_overwrite_packages'] = 'Overwrite TLD Packages';
$lang['AdminDomains.importpackages.tooltip_overwrite_packages'] = 'When checked, current Domain Manager TLD packages will be replaced by external TLD packages.  Domain Manager packages with services assigned to them will be skipped during this process.';

$lang['AdminDomains.importpackages.field_submit'] = 'Import Packages';


$lang['AdminDomains.tlds.boxtitle_tld_pricing'] = 'TLD Pricing';
$lang['AdminDomains.tlds.categorylink_tldsadd'] = 'Add TLD';
$lang['AdminDomains.tlds.heading_tld'] = 'TLD';
$lang['AdminDomains.tlds.heading_dns_management'] = 'DNS Management';
$lang['AdminDomains.tlds.heading_email_forwarding'] = 'Email Forwarding';
$lang['AdminDomains.tlds.heading_id_protection'] = 'ID Protection';
$lang['AdminDomains.tlds.heading_epp_code'] = 'EPP Code';
$lang['AdminDomains.tlds.heading_module'] = 'Module';
$lang['AdminDomains.tlds.heading_options'] = 'Options';
$lang['AdminDomains.tlds.option_edit'] = 'Edit';
$lang['AdminDomains.tlds.option_disable'] = 'Disable';
$lang['AdminDomains.tlds.option_enable'] = 'Enable';
$lang['AdminDomains.tlds.option_add'] = 'Add';
$lang['AdminDomains.tlds.confirm_disable'] = 'Are you sure you want to disable this TLD?';
$lang['AdminDomains.tlds.confirm_enable'] = 'Are you sure you want to enable this TLD?';

$lang['AdminDomains.tlds.tooltip_dns_management'] = 'The availability of DNS management will depend on whether the registrar module implements such functionality and may not be available for all TLDs or registrars';
$lang['AdminDomains.tlds.tooltip_email_forwarding'] = 'The availability of Email Forwarding will depend on whether the registrar module implements such functionality and may not be available for all TLDs or registrars';
$lang['AdminDomains.tlds.tooltip_id_protection'] = 'The availability of ID Protection will depend on whether the registrar module implements such functionality and may not be available for all TLDs or registrars';
$lang['AdminDomains.tlds.tooltip_epp_code'] = 'The availability of the EPP Code will depend on whether the registrar module implements such functionality and may not be available for all TLDs or registrars';


$lang['AdminDomains.pricing.boxtitle_edit_tld'] = 'Update TLD %1$s'; // %1$s is the TLD
$lang['AdminDomains.pricing.tab_pricing'] = 'Pricing';
$lang['AdminDomains.pricing.tab_nameservers'] = 'Name Servers';
$lang['AdminDomains.pricing.tab_welcome_email'] = 'Welcome Email';
$lang['AdminDomains.pricing.tab_advanced'] = 'Advanced';
$lang['AdminDomains.pricing.heading_term'] = 'Term';
$lang['AdminDomains.pricing.heading_register_price'] = 'Register Price';
$lang['AdminDomains.pricing.heading_renew_price'] = 'Renew Price';
$lang['AdminDomains.pricing.heading_transfer_price'] = 'Transfer Price';
$lang['AdminDomains.pricing.heading_nameservers'] = 'Nameservers';
$lang['AdminDomains.pricing.heading_module_options'] = 'Module Options';
$lang['AdminDomains.pricing.heading_advanced_options'] = 'Advanced Options';
$lang['AdminDomains.pricing.heading_welcome_email'] = 'Welcome Email';
$lang['AdminDomains.pricing.text_tags'] = 'Tags:';
$lang['AdminDomains.pricing.text_confirm_load_email'] = 'Are you sure you want to load the sample email? This will discard all changes.';
$lang['AdminDomains.pricing.text_advanced_options'] = 'Edit the core package, to define Client Limits, Configurable Options, Available Quantity, Plugin Integrations, Descriptions and more.';
$lang['AdminDomains.pricing.field_nameserver'] = 'Name Server %1$s'; // %1$s is the name server
$lang['AdminDomains.pricing.field_modulegroup_any'] = 'Any';
$lang['AdminDomains.pricing.field_edit_package'] = 'Edit Package';
$lang['AdminDomains.pricing.field_load_sample_email'] = 'Load Sample Email';
$lang['AdminDomains.pricing.field_description_html'] = 'HTML';
$lang['AdminDomains.pricing.field_description_text'] = 'Text';
$lang['AdminDomains.pricing.field_cancel'] = 'Cancel';
$lang['AdminDomains.pricing.field_update'] = 'Update';


$lang['AdminDomains.meta.boxtitle_meta_tld'] = 'Update Package Meta for TLD %1$s'; // %1$s is the TLD
$lang['AdminDomains.meta.heading_module_options'] = 'Module Options';
$lang['AdminDomains.meta.heading_update_required'] = 'Update of package meta may be required.';
$lang['AdminDomains.meta.heading_update_no_required'] = 'Update of package meta is not required for this module.';
$lang['AdminDomains.meta.text_update_required_note'] = 'When updating the registrar module of a TLD it may be necessary in some cases to update the package meta. Blesta will attempt to automatically map as many fields as possible but some fields may require a manual update.';
$lang['AdminDomains.meta.text_update_no_required_note'] = 'The new registrar module does not have package meta fields to update.';
$lang['AdminDomains.meta.field_modulegroup_any'] = 'Any';
$lang['AdminDomains.meta.field_continue'] = 'Continue';
$lang['AdminDomains.meta.field_finish'] = 'Finish';
$lang['AdminDomains.meta.field_update'] = 'Update';


$lang['AdminDomains.whois.boxtitle_whois'] = 'Whois';
$lang['AdminDomains.whois.title_row'] = 'Domain Lookup';
$lang['AdminDomains.whois.available'] = 'Domain Available';
$lang['AdminDomains.whois.unavailable'] = 'Domain Unavailable';
$lang['AdminDomains.whois.field_domain'] = 'Domain';
$lang['AdminDomains.whois.field_submit'] = 'Lookup';


$lang['AdminDomains.getDays.never'] = 'Never';
$lang['AdminDomains.getDays.text_day'] = '%1$s Day'; // %1$s is the number of days
$lang['AdminDomains.getDays.text_days'] = '%1$s Days'; // %1$s is the number of days


$lang['AdminDomains.getPeriods.day'] = 'Day';
$lang['AdminDomains.getPeriods.week'] = 'Week';
$lang['AdminDomains.getPeriods.month'] = 'Month';
$lang['AdminDomains.getPeriods.year'] = 'Year';


$lang['AdminDomains.getOperators.later'] = 'Later';
$lang['AdminDomains.getOperators.earlier'] = 'Earlier';


$lang['AdminDomains.getfilters.any'] = 'Any';
$lang['AdminDomains.getfilters.field_module_id'] = 'Registrar Modules';
$lang['AdminDomains.getfilters.field_package_name'] = 'TLD';
$lang['AdminDomains.getfilters.field_service_meta'] = 'Domain Name';


$lang['AdminDomains.leftnav.nav_domains'] = 'Domains';
$lang['AdminDomains.leftnav.nav_domains_domains'] = 'Domains';
$lang['AdminDomains.leftnav.nav_domains_whois'] = 'Whois';
$lang['AdminDomains.leftnav.nav_tlds'] = 'TLDs';
$lang['AdminDomains.leftnav.nav_tlds_pricing'] = 'TLD Pricing';
$lang['AdminDomains.leftnav.nav_tlds_registrars'] = 'Registrars';
$lang['AdminDomains.leftnav.nav_configuration'] = 'Configuration';
