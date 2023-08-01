<?php
$lang['AdminDomains.!success.registrar_upgraded'] = 'The module was successfully upgraded.';
$lang['AdminDomains.!success.registrar_installed'] = 'The module was successfully installed.';
$lang['AdminDomains.!success.registrar_uninstalled'] = 'The module was successfully uninstalled.';
$lang['AdminDomains.!success.configuration_updated'] = 'The Domain Manager configuration has been updated!';
$lang['AdminDomains.!success.tld_disabled'] = 'The TLD was successfully disabled!';
$lang['AdminDomains.!success.tld_enabled'] = 'The TLD was successfully enabled!';
$lang['AdminDomains.!success.tld_added'] = 'The TLD was successfully added!';
$lang['AdminDomains.!success.tld_updated'] = 'The TLD was successfully updated!';
$lang['AdminDomains.!success.tlds_updated'] = 'The TLDs were successfully updated!';
$lang['AdminDomains.!success.tld_sync'] = 'The TLD prices were successfully synced!';
$lang['AdminDomains.!success.change_status'] = 'The TLD statuses were successfully updated!';
$lang['AdminDomains.!success.change_auto_renewal'] = 'The Domain auto renewal has been updated!';
$lang['AdminDomains.!success.domain_renewal'] = 'The Domain has been renewed!';
$lang['AdminDomains.!success.update_nameservers'] = 'The Domain name servers has been updated!';
$lang['AdminDomains.!success.domains_pushed'] = 'The selected domains were successfully pushed to the new client.';
$lang['AdminDomains.!success.packages_imported'] = 'Domain packages imported successfully!';
$lang['AdminDomains.!success.configurable_option_updated'] = 'The configurable option has been updated!';
$lang['AdminDomains.!success.tlds_imported'] = 'The TLDs were successfully imported!';

$lang['AdminDomains.!error.value_id_invalid'] = 'The provided Value ID is not valid.';
$lang['AdminDomains.!error.tlds_bulk[action].valid'] = 'Invalid value for TLDs Bulk Action, must be "enable" or "disable".';

$lang['AdminDomains.!warning.automatic_currency_conversion'] = 'You have an exchange rate set for this currency, so it\'s not necessary to define prices here. If no prices are defined for this currency, the currency will be converted automatically from %1$s. If you define a price here, it will be used instead.'; // %1$s is the default currency
$lang['AdminDomains.!warning.price_sync_unsupported'] = 'This registrar module does not support price synchronization, TLDs will be imported without any pricing.';

$lang['AdminDomains.browse.page_title'] = 'Domains > Browse';
$lang['AdminDomains.browse.boxtitle_browse'] = 'Domains';
$lang['AdminDomains.browse.heading_domain'] = 'Domain';
$lang['AdminDomains.browse.heading_client'] = 'Client';
$lang['AdminDomains.browse.heading_registrar'] = 'Registrar';
$lang['AdminDomains.browse.heading_price'] = 'Price';
$lang['AdminDomains.browse.heading_registration'] = 'Registration Date';
$lang['AdminDomains.browse.heading_renewal'] = 'Renewal Date';
$lang['AdminDomains.browse.heading_expiration'] = 'Expiration Date';
$lang['AdminDomains.browse.heading_renew'] = 'Auto Renewal';
$lang['AdminDomains.browse.heading_options'] = 'Options';
$lang['AdminDomains.browse.option_delete'] = 'Delete';
$lang['AdminDomains.browse.option_parent'] = 'Parent';
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

$lang['AdminDomains.browse.change_auto_renewal'] = 'Change Auto Renewal';
$lang['AdminDomains.browse.domain_renewal'] = 'Renew Domain';
$lang['AdminDomains.browse.update_nameservers'] = 'Update Nameservers';
$lang['AdminDomains.browse.push_to_client'] = 'Push to Client';

$lang['AdminDomains.browse.action.field_years'] = 'Years';
$lang['AdminDomains.browse.action.field_nameservers'] = 'Nameservers';
$lang['AdminDomains.browse.action.field_client'] = 'Client';

$lang['AdminDomains.registrars.page_title'] = 'Domains > Registrars';
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


$lang['AdminDomains.configuration.page_title'] = 'Domains > Configuration';
$lang['AdminDomains.configuration.boxtitle'] = 'Configuration';
$lang['AdminDomains.configuration.tab_general'] = 'General';
$lang['AdminDomains.configuration.tab_notifications'] = 'Notifications';
$lang['AdminDomains.configuration.tab_advanced'] = 'Advanced';
$lang['AdminDomains.configuration.tab_tld_sync'] = 'TLD Sync';
$lang['AdminDomains.configuration.tab_importpackages'] = 'Import Packages';
$lang['AdminDomains.configuration.tab_configurableoptions'] = 'Configurable Options';

$lang['AdminDomains.configuration.heading_package_options'] = 'Package Options';
$lang['AdminDomains.configuration.heading_taxes'] = 'Taxes';
$lang['AdminDomains.configuration.heading_markup'] = 'Markup';
$lang['AdminDomains.configuration.heading_automation'] = 'Automation';

$lang['AdminDomains.configuration.field_dns_management_option_group'] = 'DNS Management Option Group';
$lang['AdminDomains.configuration.field_email_forwarding_option_group'] = 'Email Forwarding Option Group';
$lang['AdminDomains.configuration.field_id_protection_option_group'] = 'ID Protection Option Group';
$lang['AdminDomains.configuration.field_first_reminder_days_before'] = '1st Expiration Reminder Days Before';
$lang['AdminDomains.configuration.field_second_reminder_days_before'] = '2nd Expiration Reminder Days Before';
$lang['AdminDomains.configuration.field_expiration_notice_days_after'] = 'Expiration Notice Days After';
$lang['AdminDomains.configuration.field_spotlight_tlds'] = 'Spotlight TLDs';
$lang['AdminDomains.configuration.field_renewal_days_before_expiration'] = 'Renew Days Before Expiration';
$lang['AdminDomains.configuration.field_taxable'] = 'Enable Tax for Domains';
$lang['AdminDomains.configuration.field_override_price'] = 'Lock in domain prices';
$lang['AdminDomains.configuration.field_sync_price_markup'] = 'Price Markup (%)';
$lang['AdminDomains.configuration.field_sync_renewal_markup'] = 'Renewal Price Markup (%)';
$lang['AdminDomains.configuration.field_sync_transfer_markup'] = 'Transfer Price Markup (%)';
$lang['AdminDomains.configuration.field_enable_rounding'] = 'Enable Rounding';
$lang['AdminDomains.configuration.field_markup_rounding'] = 'Round to Next';
$lang['AdminDomains.configuration.field_automatic_sync'] = 'Enable Automated Synchronization';
$lang['AdminDomains.configuration.field_sync_frequency'] = 'Sync Every';
$lang['AdminDomains.configuration.text_manual_sync_title'] = 'Want to synchronize manually?';
$lang['AdminDomains.configuration.text_manual_sync'] = 'To synchronize TLDs manually, visit the TLD Pricing page, use checkboxes to select the TLDs to sync, and select the Registrar Sync action.';
$lang['AdminDomains.configuration.field_submit'] = 'Update Configuration';

$lang['AdminDomains.configuration.link_template'] = 'Edit Email Template';

$lang['AdminDomains.configuration.tooltip_dns_management_option_group'] = 'The configurable option group used to control whether a domain will have DNS management services.';
$lang['AdminDomains.configuration.tooltip_email_forwarding_option_group'] = 'The configurable option group used to control whether a domain will have email forwarding services.';
$lang['AdminDomains.configuration.tooltip_id_protection_option_group'] = 'The configurable option group used to control whether a domain will have ID protection services.';
$lang['AdminDomains.configuration.tooltip_override_price'] = 'When enabled this option will prevent TLD price changes from affecting existing domains by setting an "override price" on newly created domains.';
$lang['AdminDomains.configuration.tooltip_first_reminder_days_before'] = 'Select the number of days before a domain expires to send the first reminder email (26-35 as per ICANN specs). Use the Email Template link to modify/disable this email.';
$lang['AdminDomains.configuration.tooltip_second_reminder_days_before'] = 'Select the number of days before a domain expires to send the second reminder email (4-10 per ICANN specs). Use the Email Template link to modify/disable this email.';
$lang['AdminDomains.configuration.tooltip_expiration_notice_days_after'] = 'Select the number of days after a domain expires to send the expiration notice email (1-5 per ICANN specs). Use the Email Template link to modify/disable this email.';
$lang['AdminDomains.configuration.tooltip_spotlight_tlds'] = 'TLDs that will be highlighted on order forms through the Order Plugin.';
$lang['AdminDomains.configuration.tooltip_renewal_days_before_expiration'] = 'When domains are invoiced, the invoice will be due this number of days prior to the domain\'s expiration date.';

$lang['AdminDomains.getroundingoptions.custom'] = 'Custom';

$lang['AdminDomains.importpackages.page_title'] = 'Domains > Configuration';
$lang['AdminDomains.importpackages.boxtitle'] = 'Configuration';
$lang['AdminDomains.importpackages.order_form'] = 'Domain order forms should be updated to use the %1$s package group'; // %1$s is the name of the Domain Manager package group
$lang['AdminDomains.importpackages.description'] = 'This import will:<br/>
* Find all packages assigned to a registrar module (3rd party modules may not identify themselves as registrars and thus may not be recognized)<br/>
* For each TLD assigned to the package, create a new TLD Pricing package with the same details in the Domain Manager<br/>
* Skip any package/TLD with the same registrar as a previously encountered package/TLD<br/>
* Skip any packages with no yearly pricing periods<br/>
* Mark the first package/Registrar encountered for each TLD as the primary one, while the other import packages will be used when the registrar is changed on the TLD Pricing page<br/>
* Deactivate the old imported packages if they have no services after the import<br/>';

$lang['AdminDomains.importpackages.field_migrate_services'] = 'Migrate Services';
$lang['AdminDomains.importpackages.tooltip_migrate_services'] = 'When checked, any services assigned to the cloned packages will be migrated to the newly created packages.  This will only apply to services with a yearly pricing period.';
$lang['AdminDomains.importpackages.field_overwrite_packages'] = 'Overwrite TLD Packages';
$lang['AdminDomains.importpackages.tooltip_overwrite_packages'] = 'When checked, current Domain Manager TLD packages will be deleted and replaced by external TLD packages.  Domain Manager packages with services assigned to them will be skipped during this process.';
$lang['AdminDomains.importpackages.title_imported_packages'] = 'Imported TLDs';
$lang['AdminDomains.importpackages.text_collecting_list_tlds'] = 'Collecting a list of TLDs to import...';
$lang['AdminDomains.importpackages.field_submit'] = 'Import Packages';


$lang['AdminDomains.configurableoptions.page_title'] = 'Domains > Configuration';
$lang['AdminDomains.configurableoptions.boxtitle'] = 'Configuration';
$lang['AdminDomains.configurableoptions.heading_configurable_option'] = 'Configurable Option';
$lang['AdminDomains.configurableoptions.heading_options'] = 'Options';
$lang['AdminDomains.configurableoptions.option_edit'] = 'Edit';


$lang['AdminDomains.configurableoptions_pricing.boxtitle_edit_configurable_option'] = 'Edit %1$s';
$lang['AdminDomains.configurableoptions_pricing.field_update'] = 'Update';
$lang['AdminDomains.configurableoptions_pricing.field_cancel'] = 'Cancel';
$lang['AdminDomains.configurableoptions_pricing.heading_term'] = 'Term';
$lang['AdminDomains.configurableoptions_pricing.heading_price'] = 'Price';
$lang['AdminDomains.configurableoptions_pricing.heading_renew_price'] = 'Renew Price';


$lang['AdminDomains.tlds.page_title'] = 'Domains > TLDs';
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
$lang['AdminDomains.tlds.option_submit'] = 'Submit';
$lang['AdminDomains.tlds.option_configure_sync'] = 'Configure TLD Sync';
$lang['AdminDomains.tlds.field_action'] = 'Action';
$lang['AdminDomains.tlds.field_status'] = 'Status';
$lang['AdminDomains.tlds.confirm_disable'] = 'Are you sure you want to disable this TLD?';
$lang['AdminDomains.tlds.confirm_enable'] = 'Are you sure you want to enable this TLD?';

$lang['AdminDomains.tlds.tooltip_dns_management'] = 'The availability of DNS management will depend on whether the registrar module implements such functionality and may not be available for all TLDs or registrars';
$lang['AdminDomains.tlds.tooltip_email_forwarding'] = 'The availability of Email Forwarding will depend on whether the registrar module implements such functionality and may not be available for all TLDs or registrars';
$lang['AdminDomains.tlds.tooltip_id_protection'] = 'The availability of ID Protection will depend on whether the registrar module implements such functionality and may not be available for all TLDs or registrars';
$lang['AdminDomains.tlds.tooltip_epp_code'] = 'The availability of the EPP Code will depend on whether the registrar module implements such functionality and may not be available for all TLDs or registrars';


// Get TLD Actions
$lang['AdminDomains.getTldActions.option_change_status'] = 'Change Status';
$lang['AdminDomains.getTldActions.option_tld_sync'] = 'Registrar Price Sync';


// Get TLD Statuses
$lang['AdminDomains.getTldStatuses.option_disabled'] = 'Disabled';
$lang['AdminDomains.getTldStatuses.option_enabled'] = 'Enabled';


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


$lang['AdminDomains.whois.page_title'] = 'Domains > Whois';
$lang['AdminDomains.whois.boxtitle_whois'] = 'Whois';
$lang['AdminDomains.whois.title_row'] = 'Domain Lookup';
$lang['AdminDomains.whois.available'] = 'Domain Available';
$lang['AdminDomains.whois.unavailable'] = 'Domain Unavailable';
$lang['AdminDomains.whois.field_domain'] = 'Domain';
$lang['AdminDomains.whois.field_submit'] = 'Lookup';


$lang['AdminDomains.import.boxtitle_import'] = 'Import TLDs';
$lang['AdminDomains.import.title_module'] = 'Module';
$lang['AdminDomains.import.title_terms'] = 'Terms';
$lang['AdminDomains.import.title_tlds'] = 'TLDs';
$lang['AdminDomains.import.field_module'] = 'Module';
$lang['AdminDomains.import.field_import'] = 'Import TLDs';
$lang['AdminDomains.import.text_refresh'] = 'Refresh';
$lang['AdminDomains.import.text_install_modules'] = 'Install Modules';
$lang['AdminDomains.import.text_tld_settings'] = 'TLD pricing markups, round, etc., will be set based on the TLD Sync settings.';
$lang['AdminDomains.import.text_configuration'] = 'Configure Settings';
$lang['AdminDomains.import.text_year'] = '%1$s Year'; // %1$s is the number of years
$lang['AdminDomains.import.text_years'] = '%1$s Years'; // %1$s is the number of years
$lang['AdminDomains.import.text_terms_notice'] = 'At least one term must be selected.';


$lang['AdminDomains.getDays.same_day'] = 'Same Day';
$lang['AdminDomains.getDays.text_day'] = '%1$s Day'; // %1$s is the number of days
$lang['AdminDomains.getDays.text_days'] = '%1$s Days'; // %1$s is the number of days


$lang['AdminDomains.getPeriods.day'] = 'Day';
$lang['AdminDomains.getPeriods.week'] = 'Week';
$lang['AdminDomains.getPeriods.month'] = 'Month';
$lang['AdminDomains.getPeriods.year'] = 'Year';


$lang['AdminDomains.getOperators.later'] = 'Later';
$lang['AdminDomains.getOperators.earlier'] = 'Earlier';


$lang['AdminDomains.getfilters.any'] = 'Any';
$lang['AdminDomains.getfilters.field_module_id'] = 'Registrar Module';
$lang['AdminDomains.getfilters.field_package_name'] = 'TLD';
$lang['AdminDomains.getfilters.field_service_meta'] = 'Domain Name';


$lang['AdminDomains.gettldfilters.any'] = 'Any';
$lang['AdminDomains.gettldfilters.field_module_id'] = 'Registrar Module';
$lang['AdminDomains.gettldfilters.field_search_tld'] = 'TLD';
$lang['AdminDomains.gettldfilters.field_limit'] = 'Limit';


$lang['AdminDomains.leftnav.nav_utilities'] = 'Utilities';
$lang['AdminDomains.leftnav.nav_domains_whois'] = 'Whois';
$lang['AdminDomains.leftnav.nav_tlds'] = 'TLDs';
$lang['AdminDomains.leftnav.nav_tlds_pricing'] = 'TLD Pricing';
$lang['AdminDomains.leftnav.nav_tlds_registrars'] = 'Registrars';
$lang['AdminDomains.leftnav.nav_tlds_import'] = 'Import TLDs';
$lang['AdminDomains.leftnav.nav_configuration'] = 'Configuration';
