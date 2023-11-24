<?php
// Success messages
$lang['AdminMain.!success.change_auto_renewal'] = 'The Domain auto renewal has been updated!';
$lang['AdminMain.!success.domain_renewal'] = 'The Domain has been renewed!';
$lang['AdminMain.!success.update_nameservers'] = 'The Domain name servers has been updated!';
$lang['AdminMain.!success.domains_pushed'] = 'The selected domains were successfully pushed to the new client.';
$lang['AdminMain.!success.domains_unparented'] = 'The selected domains were removed from their parent services and the price has been reset successfully!';

$lang['AdminMain.!success.domain_register'] = 'The domain has been registered successfully!';
$lang['AdminMain.!success.domain_transfer'] = 'The domain has been transferred successfully!';
$lang['AdminMain.!success.domain_add'] = 'The domain has been added successfully!';
$lang['AdminMain.!success.service_edited'] = 'The domain has been updated successfully!';


// Error messages
$lang['AdminMain.!error.unsupported_domain'] = 'The domain name is not supported.';


// Domains
$lang['AdminMain.index.page_title'] = 'Client #%1$s Domains'; // %1$s is the client ID number
$lang['AdminMain.index.boxtitle_domains'] = 'Domains';

$lang['AdminMain.index.heading_addons'] = 'Add-ons';
$lang['AdminMain.index.heading_status'] = 'Status';

$lang['AdminMain.index.heading_domain'] = 'Domain';
$lang['AdminMain.index.heading_term'] = 'Term';
$lang['AdminMain.index.heading_dateregistration'] = 'Date Registered';
$lang['AdminMain.index.heading_daterenews'] = 'Date Renews';
$lang['AdminMain.index.heading_dateexpires'] = 'Date Expires';
$lang['AdminMain.index.heading_datesuspended'] = 'Date Suspended';
$lang['AdminMain.index.heading_datecanceled'] = 'Date Canceled';
$lang['AdminMain.index.heading_options'] = 'Options';

$lang['AdminMain.index.category_active'] = 'Active';
$lang['AdminMain.index.category_pending'] = 'Pending';
$lang['AdminMain.index.category_suspended'] = 'Suspended';
$lang['AdminMain.index.category_canceled'] = 'Canceled';

$lang['AdminMain.index.categorylink_newservice'] = 'New Domain';

$lang['AdminMain.index.recurring_term'] = '%1$s %2$s @ %3$s'; // %1$s is the service term length (number), %2$s is the service period, %3$s is the formatted service renewal price
$lang['AdminMain.index.option_parent'] = 'Parent';
$lang['AdminMain.index.option_manage'] = 'Manage';
$lang['AdminMain.index.option_delete'] = 'Delete';
$lang['AdminMain.index.confirm_delete'] = 'Are you sure you want to delete this service?';
$lang['AdminMain.index.no_results'] = 'There are no services with this status.';

$lang['AdminMain.index.text_never'] = 'Never';

$lang['AdminMain.index.text_on'] = 'On';
$lang['AdminMain.index.text_off'] = 'Off';
$lang['AdminMain.index.change_auto_renewal'] = 'Change Auto Renewal';
$lang['AdminMain.index.domain_renewal'] = 'Renew Domain';
$lang['AdminMain.index.update_nameservers'] = 'Update Nameservers';
$lang['AdminMain.index.domain_push_to_client'] = 'Push to Client';
$lang['AdminMain.index.unparent'] = 'Unparent and Reset Price';
$lang['AdminMain.index.field_actionsubmit'] = 'Submit';

$lang['AdminMain.domains.action.field_years'] = 'Years';
$lang['AdminMain.domains.action.field_nameservers'] = 'Nameservers';
$lang['AdminMain.domains.action.field_client'] = 'Client';


// Add domain
$lang['AdminMain.add.boxtitle_client'] = 'Client #%1$s'; // %1$s is the Client ID
$lang['AdminMain.add.boxtitle_add'] = 'Add Domain: %1$s'; // %1$s is the TLD of the domain

$lang['AdminMain.add.link_viewclient'] = 'View Client';

$lang['AdminMain.add.field_transfer'] = 'Transfer';
$lang['AdminMain.add.field_register'] = 'Register';
$lang['AdminMain.add.field_add'] = 'Add Domain';
$lang['AdminMain.add.field_lookup'] = 'Check Availability';
$lang['AdminMain.add.field_submit'] = 'Continue';
$lang['AdminMain.add.field_invoice_method'] = 'Invoice Method';
$lang['AdminMain.add.field_invoice_method_create'] = 'Create New Invoice';
$lang['AdminMain.add.field_invoice_method_append'] = 'Append to Existing Invoice';
$lang['AdminMain.add.field_invoice_method_dont'] = 'Do Not Invoice';
$lang['AdminMain.add.field_years'] = 'Years';
$lang['AdminMain.add.field_status'] = 'Status';
$lang['AdminMain.add.field_module'] = 'Registrar Module';
$lang['AdminMain.add.field_use_module'] = 'Provision the domain using the registrar module when activated';
$lang['AdminMain.add.field_notify_order'] = 'Send order confirmation email when activated';

$lang['AdminMain.add.title_search_results'] = 'Search Results';
$lang['AdminMain.add.title_basic_options'] = 'Basic Options';
$lang['AdminMain.add.title_registrar_options'] = 'Registrar Options';

$lang['AdminMain.add.heading_domain'] = 'Domain';
$lang['AdminMain.add.heading_status'] = 'Status';
$lang['AdminMain.add.heading_options'] = 'Options';

$lang['AdminMain.add.text_domain_available'] = 'Available';
$lang['AdminMain.add.text_domain_unavailable'] = 'Unavailable';

$lang['AdminMain.add.term_day'] = '%1$s Day'; // %1$s is the term
$lang['AdminMain.add.term_days'] = '%1$s Days'; // %1$s is the term
$lang['AdminMain.add.term_week'] = '%1$s Week'; // %1$s is the term
$lang['AdminMain.add.term_weeks'] = '%1$s Weeks'; // %1$s is the term
$lang['AdminMain.add.term_month'] = '%1$s Month'; // %1$s is the term
$lang['AdminMain.add.term_months'] = '%1$s Months'; // %1$s is the term
$lang['AdminMain.add.term_year'] = '%1$s Year'; // %1$s is the term
$lang['AdminMain.add.term_years'] = '%1$s Years'; // %1$s is the term

$lang['AdminMain.add.order_btn'] = 'Order Selected';
$lang['AdminMain.add.term'] = '%1$s @ %2$s'; // %1$s is the term, %2$s is the currency price
$lang['AdminMain.add.term_recurring'] = '%1$s @ %2$s (renews @ %3$s)'; // %1$s is the term, %2$s is the initial price, %3$s is the renewal price
$lang['AdminMain.add.edit_package_pricing'] = 'Edit Pricing';


// Domain confirmation
$lang['AdminMain.add_confirmation.field_invoice_method'] = 'Invoice Method:';
$lang['AdminMain.add_confirmation.field_invoice_method_create'] = 'Create New Invoice';
$lang['AdminMain.add_confirmation.field_invoice_method_append'] = 'Append to Invoice %1$s';
$lang['AdminMain.add_confirmation.field_invoice_method_dont'] = 'Do Not Invoice';
$lang['AdminMain.add_confirmation.field_status'] = 'Status:';
$lang['AdminMain.add_confirmation.field_module'] = 'Registrar Module:';
$lang['AdminMain.add_confirmation.field_notify_order'] = 'Send Order Confirmation Email:';
$lang['AdminMain.add_confirmation.field_notify_order_true'] = 'Yes';
$lang['AdminMain.add_confirmation.field_notify_order_false'] = 'No';
$lang['AdminMain.add_confirmation.field_domain'] = 'Domain:';
$lang['AdminMain.add_confirmation.field_years'] = 'Years:';
$lang['AdminMain.add_confirmation.field_type'] = 'Type:';
$lang['AdminMain.add_confirmation.field_price'] = 'Price:';
$lang['AdminMain.add_confirmation.field_coupon_code'] = 'Coupon Code';
$lang['AdminMain.add_confirmation.field_update_coupon'] = 'Update';
$lang['AdminMain.add_confirmation.field_add'] = 'Add Domain';
$lang['AdminMain.add_confirmation.field_edit'] = 'Edit';

$lang['AdminMain.add_confirmation.type_register'] = 'Registration';
$lang['AdminMain.add_confirmation.type_transfer'] = 'Transfer';

$lang['AdminMain.add_confirmation.description'] = 'Description';
$lang['AdminMain.add_confirmation.qty'] = 'Quantity';
$lang['AdminMain.add_confirmation.price'] = 'Price';
$lang['AdminMain.add_confirmation.subtotal'] = 'Sub Total:';
$lang['AdminMain.add_confirmation.discount'] = 'Discount:';


// Edit domain
$lang['AdminMain.edit.boxtitle_client'] = 'Client #%1$s'; // %1$s is the Client ID
$lang['AdminMain.edit.boxtitle_edit'] = 'Edit Domain: %1$s'; // %1$s is the TLD of the domain

$lang['AdminMain.edit.link_viewclient'] = 'View Client';

$lang['AdminMain.edit.field_action'] = 'Action';
$lang['AdminMain.edit.field_years'] = 'Years';
$lang['AdminMain.edit.field_auto_renewal'] = 'Enable Auto-Renewal';
$lang['AdminMain.edit.field_ns1'] = 'Name Server 1';
$lang['AdminMain.edit.field_ns2'] = 'Name Server 2';
$lang['AdminMain.edit.field_ns3'] = 'Name Server 3';
$lang['AdminMain.edit.field_ns4'] = 'Name Server 4';
$lang['AdminMain.edit.field_ns5'] = 'Name Server 5';
$lang['AdminMain.edit.field_client'] = 'Client';
$lang['AdminMain.edit.field_module'] = 'Registrar Module';
$lang['AdminMain.edit.field_use_module'] = 'Use module';
$lang['AdminMain.edit.field_notify_order'] = 'Send order confirmation email when activated';
$lang['AdminMain.edit.field_submit'] = 'Update';
$lang['AdminMain.edit.field_activate'] = 'Activate';
$lang['AdminMain.edit.field_edit_service'] = 'Edit Service';

$lang['AdminMain.edit.text_domain'] = 'Domain:';
$lang['AdminMain.edit.text_registrar'] = 'Registrar:';
$lang['AdminMain.edit.text_years'] = 'Registered Years:';
$lang['AdminMain.edit.text_status'] = 'Status:';
$lang['AdminMain.edit.text_date_added'] = 'Date Created:';
$lang['AdminMain.edit.text_registration_date'] = 'Date Registered:';
$lang['AdminMain.edit.text_date_renews'] = 'Date Renews:';
$lang['AdminMain.edit.text_date_expires'] = 'Date Expires:';
$lang['AdminMain.edit.text_never'] = 'Never';
$lang['AdminMain.edit.text_date_last_renewed'] = 'Last Renewed Date:';
$lang['AdminMain.edit.text_date_suspended'] = 'Suspension Date:';
$lang['AdminMain.edit.text_date_canceled'] = 'Cancellation Date:';

$lang['AdminMain.edit.term_day'] = '%1$s Day'; // %1$s is the term
$lang['AdminMain.edit.term_days'] = '%1$s Days'; // %1$s is the term
$lang['AdminMain.edit.term_week'] = '%1$s Week'; // %1$s is the term
$lang['AdminMain.edit.term_weeks'] = '%1$s Weeks'; // %1$s is the term
$lang['AdminMain.edit.term_month'] = '%1$s Month'; // %1$s is the term
$lang['AdminMain.edit.term_months'] = '%1$s Months'; // %1$s is the term
$lang['AdminMain.edit.term_year'] = '%1$s Year'; // %1$s is the term
$lang['AdminMain.edit.term_years'] = '%1$s Years'; // %1$s is the term

$lang['AdminMain.edit.title_domain_information'] = 'Domain Information';
$lang['AdminMain.edit.title_actions'] = 'Actions';
$lang['AdminMain.edit.title_basic_options'] = 'Basic Options';


// Get filters
$lang['AdminMain.getfilters.any'] = 'Any';
$lang['AdminMain.getfilters.field_package_name'] = 'TLD';
$lang['AdminMain.getfilters.field_service_meta'] = 'Domain Name';


// Get module fields
$lang['AdminMain.getmodulefields.auto_choose'] = '-- Choose Automatically --';
