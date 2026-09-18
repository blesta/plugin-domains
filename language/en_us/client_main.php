<?php
$lang['ClientMain.index.page_title'] = 'Domains';

// Index
$lang['ClientMain.index.page_title'] = 'Client #%1$s Domains'; // %1$s is the client ID number
$lang['ClientMain.index.boxtitle_domains'] = 'Domains';
$lang['ClientMain.index.boxtitle_registered_domains'] = 'Registered Domains';
$lang['ClientMain.index.link_viewall'] = 'View all';
$lang['ClientMain.index.category_active'] = 'Active';
$lang['ClientMain.index.category_pending'] = 'Pending';
$lang['ClientMain.index.category_suspended'] = 'Suspended';
$lang['ClientMain.index.category_deleted'] = 'Deleted';
$lang['ClientMain.index.heading_addons'] = 'Add-ons';
$lang['ClientMain.index.heading_package'] = 'Package';
$lang['ClientMain.index.heading_label'] = 'Label';
$lang['ClientMain.index.heading_status'] = 'Status';
$lang['ClientMain.index.heading_domain'] = 'Domain';
$lang['ClientMain.index.heading_term'] = 'Term';
$lang['ClientMain.index.heading_registration_date'] = 'Registration Date';
$lang['ClientMain.index.heading_renewal_date'] = 'Renewal Date';
$lang['ClientMain.index.heading_expiration_date'] = 'Expiration Date';
$lang['ClientMain.index.heading_suspension_date'] = 'Suspension Date';
$lang['ClientMain.index.heading_deletion_date'] = 'Deletion Date';
$lang['ClientMain.index.heading_options'] = 'Actions';
$lang['ClientMain.index.option_manage'] = 'Manage';
$lang['ClientMain.index.recurring_term'] = '%1$s %2$s @ %3$s'; // %1$s is the service term length (number), %2$s is the service period, %3$s is the formatted service renewal price
$lang['ClientMain.index.text_never'] = 'Never';
$lang['ClientMain.index.text_renews'] = 'Renews %1$s'; // %1$s is the formatted domain renewal date
$lang['ClientMain.index.no_results'] = 'You have no %1$s Domains.'; // %1$s is the language for the domains category type (e.g. Active, Pending)

// Service info
$lang['ClientMain.serviceinfo.no_results'] = 'This domain has no details.';
$lang['ClientMain.serviceinfo.parent_service'] = 'This domain belongs to <a href="%1$s">%2$s (%3$s)</a>. If that service is cancelled, this domain will be cancelled as well.'; // %1$s is the link to the parent service, %2$s is the service package and %3$s is the service label
$lang['ClientMain.serviceinfo.cancellation_reason'] = 'Reason for Cancellation: %1$s'; // %1$s is the reason this service was canceled

// Get filters
$lang['ClientMain.getfilters.any'] = 'Any';
$lang['ClientMain.getfilters.field_module_id'] = 'Registrar Modules';
$lang['ClientMain.getfilters.field_package_name'] = 'TLD';
$lang['ClientMain.getfilters.field_service_meta'] = 'Domain Name';

// Manage
$lang['ClientMain.manage.page_title'] = 'Manage %1$s'; // %1$s is the domain name
$lang['ClientMain.manage.page_heading'] = 'Manage Domain';
$lang['ClientMain.manage.boxtitle_manage'] = 'Manage %1$s'; // %1$s is the domain name
$lang['ClientMain.manage.heading_domain_details'] = 'Domain Details';
$lang['ClientMain.manage.heading_domain'] = 'Domain';
$lang['ClientMain.manage.heading_registration_date'] = 'Registration Date';
$lang['ClientMain.manage.heading_expiration_date'] = 'Expiration Date';
$lang['ClientMain.manage.heading_package_term'] = 'Term';
$lang['ClientMain.manage.heading_auto_renewal'] = 'Auto-Renewal';
$lang['ClientMain.manage.heading_nameservers'] = 'Name Servers';
$lang['ClientMain.manage.heading_contacts'] = 'Contact Information';
$lang['ClientMain.manage.heading_registrar_lock'] = 'Registrar Lock';
$lang['ClientMain.manage.heading_expiration'] = 'Expiration';
$lang['ClientMain.manage.heading_recurring_amount'] = 'Recurring Amount';
$lang['ClientMain.manage.heading_whois'] = 'WHOIS Information';
$lang['ClientMain.manage.heading_registrant'] = 'Registrant';
$lang['ClientMain.manage.heading_organization'] = 'Organization';
$lang['ClientMain.manage.heading_email'] = 'Email';
$lang['ClientMain.manage.heading_id_protection'] = 'ID Protection';
$lang['ClientMain.manage.heading_epp_code'] = 'EPP Code';

$lang['ClientMain.manage.tab_domain_info'] = 'Domain Information';
$lang['ClientMain.manage.tab_domain_return'] = 'Back to Domains';
$lang['ClientMain.manage.tab_service_return'] = 'Back to %1$s'; // %1$s is the domain name

$lang['ClientMain.manage.text_never'] = 'Never';
$lang['ClientMain.manage.text_optional'] = 'Optional';
$lang['ClientMain.manage.text_term'] = '%1$s term'; // %1$s is the package term, e.g. "1 Year"
$lang['ClientMain.manage.text_renews_automatically'] = 'Renews automatically';
$lang['ClientMain.manage.text_renews_manually'] = 'Does not renew automatically';
$lang['ClientMain.manage.text_transfer_protection_enabled'] = 'Transfer protection enabled';
$lang['ClientMain.manage.text_transfer_protection_disabled'] = 'Transfer protection disabled';
$lang['ClientMain.manage.text_id_protection_enabled'] = 'Enabled';
$lang['ClientMain.manage.text_id_protection_disabled'] = 'Disabled';
$lang['ClientMain.manage.text_date_to_cancel'] = 'This domain is scheduled to be canceled on %1$s.'; // %1$s is the formatted cancellation date
$lang['ClientMain.manage.text_auto_renewal_on'] = 'Enabled';
$lang['ClientMain.manage.text_auto_renewal_off'] = 'Disabled';
$lang['ClientMain.manage.text_auto_renewal_info'] = 'When auto-renewal is disabled the domain will not be renewed and will be canceled at the end of its term.';
$lang['ClientMain.manage.text_locked'] = 'Locked';
$lang['ClientMain.manage.text_unlocked'] = 'Unlocked';
$lang['ClientMain.manage.text_nameservers_info'] = 'Name servers control which DNS servers are authoritative for this domain. Leave a field blank to remove it.';
$lang['ClientMain.manage.text_no_contacts'] = 'The registrar did not return any contact information for this domain.';
$lang['ClientMain.manage.text_registrar_lock_info'] = 'A registrar lock prevents the domain from being transferred to another registrar.';
$lang['ClientMain.manage.text_epp_code_info'] = 'Request the EPP (authorization) code for this domain. It will be emailed to the registrant contact.';

$lang['ClientMain.manage.field_auto_renewal'] = 'Auto-Renewal';
$lang['ClientMain.manage.field_registrar_lock'] = 'Registrar Lock';
$lang['ClientMain.manage.field_nameserver'] = 'Name Server %1$s'; // %1$s is the name server number
$lang['ClientMain.manage.field_submit'] = 'Update';
$lang['ClientMain.manage.field_nameservers_submit'] = 'Update Name Servers';
$lang['ClientMain.manage.field_epp_submit'] = 'Request EPP Code';
$lang['ClientMain.manage.field_modal_cancel'] = 'Close';

$lang['ClientMain.manage.field_contact_first_name'] = 'First Name';
$lang['ClientMain.manage.field_contact_last_name'] = 'Last Name';
$lang['ClientMain.manage.field_contact_email'] = 'Email';
$lang['ClientMain.manage.field_contact_phone'] = 'Phone';
$lang['ClientMain.manage.field_contact_address1'] = 'Address 1';
$lang['ClientMain.manage.field_contact_address2'] = 'Address 2';
$lang['ClientMain.manage.field_contact_city'] = 'City';
$lang['ClientMain.manage.field_contact_state'] = 'State';
$lang['ClientMain.manage.field_contact_zip'] = 'Zip/Postal Code';
$lang['ClientMain.manage.field_contact_country'] = 'Country';

$lang['ClientMain.manage.button_auto_renewal'] = 'Auto-Renewal';
$lang['ClientMain.manage.button_nameservers'] = 'Name Servers';
$lang['ClientMain.manage.button_registrar_lock'] = 'Registrar Lock';
$lang['ClientMain.manage.button_contacts'] = 'Contact Information';
$lang['ClientMain.manage.button_epp_code'] = 'EPP Code';
$lang['ClientMain.manage.button_renew'] = 'Renew Domain';
$lang['ClientMain.manage.button_cancel'] = 'Cancel Domain';
$lang['ClientMain.manage.button_back_to_domains'] = 'Back to Domains';

// Renew
$lang['ClientMain.renew.term'] = '%1$s Year (%2$s)'; // %1$s is the number of years, %2$s is the formatted renewal price
$lang['ClientMain.renew.terms'] = '%1$s Years (%2$s)'; // %1$s is the number of years, %2$s is the formatted renewal price
$lang['ClientMain.renew.confirm_renew'] = 'Renewing this domain for %1$s will extend its renewal date to %2$s. An invoice will be created for you to pay.'; // %1$s is the renewal term, %2$s is the new renewal date

// Success/Error messages
$lang['ClientMain.!success.auto_renewal'] = 'The auto-renewal setting was successfully updated.';
$lang['ClientMain.!success.nameservers'] = 'The name servers were successfully updated.';
$lang['ClientMain.!success.registrar_lock'] = 'The registrar lock was successfully updated.';
$lang['ClientMain.!success.contacts'] = 'The contact information was successfully updated.';
$lang['ClientMain.!success.epp'] = 'The EPP code was successfully requested and will be emailed to the registrant contact.';
$lang['ClientMain.!success.domain_renewed'] = 'The domain was successfully queued for renewal. Please pay the invoice to complete the renewal.';

$lang['ClientMain.!error.invalid_section'] = 'The requested action is not valid.';
$lang['ClientMain.!error.unsupported'] = 'The registrar does not support this action.';
