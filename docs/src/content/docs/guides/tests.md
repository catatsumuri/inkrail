---
title: Test inventory
description: Inventory of the 240 committed test methods, grouped by test file.
---

<div class="test-inventory">

This inventory covers **240 test methods in 25 committed files** (238 Feature methods and 2 Unit methods). Keys preserve the exact English method names.

Counts refer to methods. Data providers run some methods with multiple inputs, so the executed case count can differ. This inventory describes implemented checks; it does not report the latest test results or guarantee behavior in every configuration.

For commands, see [Tests and static analysis](../#tests-and-static-analysis). CI compares this inventory and its Japanese counterpart with the Git-tracked test methods using `node scripts/check-test-inventory.mjs`.

## Feature/Auth/AuthenticationTest

[Feature/Auth/AuthenticationTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Auth/AuthenticationTest.php) — 6 methods

| Test key |
| --- |
| `test_login_screen_can_be_rendered` |
| `test_users_can_authenticate_using_the_login_screen` |
| `test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge` |
| `test_users_can_not_authenticate_with_invalid_password` |
| `test_users_can_logout` |
| `test_users_are_rate_limited` |

## Feature/Auth/RegistrationTest

[Feature/Auth/RegistrationTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Auth/RegistrationTest.php) — 2 methods

| Test key |
| --- |
| `test_registration_screen_can_be_rendered` |
| `test_new_users_can_register` |

## Feature/Auth/EmailVerificationTest

[Feature/Auth/EmailVerificationTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Auth/EmailVerificationTest.php) — 7 methods

| Test key |
| --- |
| `test_email_verification_screen_can_be_rendered` |
| `test_unverified_users_are_redirected_to_the_email_verification_prompt` |
| `test_email_can_be_verified` |
| `test_email_is_not_verified_with_invalid_hash` |
| `test_email_is_not_verified_with_invalid_user_id` |
| `test_verified_user_is_redirected_to_dashboard_from_verification_prompt` |
| `test_already_verified_user_visiting_verification_link_is_redirected_without_firing_event_again` |

## Feature/Auth/VerificationNotificationTest

[Feature/Auth/VerificationNotificationTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Auth/VerificationNotificationTest.php) — 2 methods

| Test key |
| --- |
| `test_sends_verification_notification` |
| `test_does_not_send_verification_notification_if_email_is_verified` |

## Feature/Auth/PasswordConfirmationTest

[Feature/Auth/PasswordConfirmationTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Auth/PasswordConfirmationTest.php) — 2 methods

| Test key |
| --- |
| `test_confirm_password_screen_can_be_rendered` |
| `test_password_confirmation_requires_authentication` |

## Feature/Auth/PasswordResetTest

[Feature/Auth/PasswordResetTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Auth/PasswordResetTest.php) — 5 methods

| Test key |
| --- |
| `test_reset_password_link_screen_can_be_rendered` |
| `test_reset_password_link_can_be_requested` |
| `test_reset_password_screen_can_be_rendered` |
| `test_password_can_be_reset_with_valid_token` |
| `test_password_cannot_be_reset_with_invalid_token` |

## Feature/Auth/PasswordResetThrottleTest

[Feature/Auth/PasswordResetThrottleTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Auth/PasswordResetThrottleTest.php) — 5 methods

| Test key |
| --- |
| `test_failed_resets_within_the_limit_are_all_recorded` |
| `test_repeated_attempts_for_one_account_are_throttled_without_more_log_rows` |
| `test_one_address_cannot_flood_the_log_by_varying_the_email` |
| `test_a_throttled_account_does_not_block_other_accounts` |
| `test_requesting_a_reset_link_is_not_affected_by_the_reset_limit` |

## Feature/Auth/TwoFactorChallengeTest

[Feature/Auth/TwoFactorChallengeTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Auth/TwoFactorChallengeTest.php) — 2 methods

| Test key |
| --- |
| `test_two_factor_challenge_redirects_to_login_when_not_authenticated` |
| `test_two_factor_challenge_can_be_rendered` |

## Feature/DashboardTest

[Feature/DashboardTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/DashboardTest.php) — 2 methods

| Test key |
| --- |
| `test_guests_are_redirected_to_the_login_page` |
| `test_authenticated_users_can_visit_the_dashboard` |

## Feature/Settings/ProfileUpdateTest

[Feature/Settings/ProfileUpdateTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Settings/ProfileUpdateTest.php) — 15 methods

| Test key |
| --- |
| `test_profile_page_is_displayed` |
| `test_profile_information_can_be_updated` |
| `test_email_cannot_be_changed_without_the_current_password` |
| `test_email_cannot_be_changed_with_an_incorrect_password` |
| `test_current_password_cannot_be_brute_forced_through_the_profile_update` |
| `test_profile_update_throttling_does_not_affect_other_users` |
| `test_email_cannot_be_changed_by_letter_case_without_the_current_password` |
| `test_array_email_is_rejected_with_a_validation_error` |
| `test_empty_email_is_rejected_with_a_validation_error` |
| `test_array_current_password_is_rejected_with_a_validation_error` |
| `test_email_is_not_changed_by_extra_request_fields` |
| `test_name_can_be_changed_without_the_current_password` |
| `test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged` |
| `test_user_can_delete_their_account` |
| `test_correct_password_must_be_provided_to_delete_account` |

## Feature/Settings/SecurityTest

[Feature/Settings/SecurityTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Settings/SecurityTest.php) — 5 methods

| Test key |
| --- |
| `test_security_page_is_displayed` |
| `test_security_page_requires_password_confirmation_when_enabled` |
| `test_security_page_renders_without_two_factor_when_feature_is_disabled` |
| `test_password_can_be_updated` |
| `test_correct_password_must_be_provided_to_update_password` |

## Feature/Settings/SessionManagementTest

[Feature/Settings/SessionManagementTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Settings/SessionManagementTest.php) — 49 methods

| Test key |
| --- |
| `test_user_can_view_their_current_and_other_active_sessions` |
| `test_guests_cannot_access_session_management` |
| `test_the_session_page_itself_requires_recent_password_confirmation` |
| `test_confirming_the_password_returns_to_the_page_and_revocation_then_needs_no_second_prompt` |
| `test_viewing_the_session_page_is_not_throttled_like_revocation` |
| `test_session_presenter_marks_the_current_session_and_masks_its_details` |
| `test_session_page_is_safe_when_database_sessions_are_not_available` |
| `test_session_page_is_safe_when_session_storage_is_inaccessible` |
| `test_other_sessions_are_paginated` |
| `test_user_can_revoke_another_session` |
| `test_user_cannot_revoke_another_users_session` |
| `test_user_can_revoke_all_other_sessions` |
| `test_only_active_sessions_can_be_revoked_individually` |
| `test_revoking_other_sessions_preserves_the_supplied_current_session` |
| `test_deleting_a_database_row_invalidates_every_copy_of_the_session_id` |
| `test_user_can_revoke_all_sessions_including_the_current_session` |
| `test_revocation_is_unavailable_for_non_database_session_drivers` |
| `test_revocation_requires_password_confirmation_within_fifteen_minutes` |
| `test_revocation_without_any_confirmation_is_redirected_to_confirm_password` |
| `test_revocation_is_allowed_within_the_confirmation_window` |
| `test_revocation_endpoints_are_rate_limited` |
| `test_unknown_and_foreign_fingerprints_get_the_same_response` |
| `test_session_ids_are_not_exposed_to_the_frontend` |
| `test_storage_failure_while_revoking_reports_an_error_and_does_not_sign_the_user_out` |
| `test_current_session_cannot_be_revoked_through_the_individual_endpoint` |
| `test_revoking_another_session_signs_out_the_original_and_copied_cookie` |
| `test_revoking_other_sessions_signs_out_every_other_client_but_keeps_the_caller` |
| `test_revoking_all_sessions_signs_out_every_client_including_the_caller` |
| `test_a_revoked_client_cannot_revoke_anything` |
| `test_revoking_other_sessions_also_invalidates_other_remember_me_cookies` |
| `test_revoking_other_sessions_invalidates_remember_me_cookies_even_when_no_other_session_is_stored` |
| `test_revoking_one_session_also_ends_remember_me_logins_so_it_cannot_come_back` |
| `test_revoking_an_unknown_or_foreign_session_does_not_touch_the_remember_token` |
| `test_failing_to_reset_the_remember_token_keeps_the_session_when_revoking_one` |
| `test_a_failing_delete_while_revoking_one_session_changes_nothing` |
| `test_a_failing_token_update_rolls_back_the_deleted_session_when_revoking_one` |
| `test_a_declined_token_update_rolls_back_the_deleted_session_when_revoking_one` |
| `test_a_session_that_vanishes_before_deletion_does_not_reset_the_remember_token` |
| `test_a_failing_delete_while_revoking_one_session_reports_a_partial_result_on_a_separate_connection` |
| `test_a_session_that_vanishes_before_deletion_is_reported_as_partial_on_a_separate_connection` |
| `test_revocation_works_with_sessions_stored_on_an_independent_database_connection` |
| `test_a_failing_delete_while_revoking_other_sessions_changes_nothing` |
| `test_a_failing_delete_while_revoking_other_sessions_reports_a_partial_result_on_a_separate_connection` |
| `test_a_failing_delete_while_revoking_all_sessions_changes_nothing_and_keeps_the_user_signed_in` |
| `test_a_failing_delete_while_revoking_all_sessions_reports_a_partial_result_on_a_separate_connection` |
| `test_a_failing_token_update_rolls_back_the_deleted_sessions_when_revoking_others_and_all` |
| `test_a_declined_token_update_rolls_back_the_deleted_sessions_when_revoking_others_and_all` |
| `test_revoking_all_sessions_also_invalidates_remember_me_cookies` |
| `test_failing_to_reset_the_remember_token_does_not_report_success_or_sign_the_user_out` |

## Feature/Console/RevokeUserSessionsCommandTest

[Feature/Console/RevokeUserSessionsCommandTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Console/RevokeUserSessionsCommandTest.php) — 23 methods

| Test key |
| --- |
| `test_a_selector_must_state_its_type_and_a_valid_value` |
| `test_an_unknown_user_fails_without_touching_other_sessions` |
| `test_declining_the_confirmation_preserves_every_session` |
| `test_confirming_revokes_every_session_of_the_user` |
| `test_force_revokes_only_the_target_users_sessions_including_expired_rows` |
| `test_a_user_without_stored_sessions_still_requires_confirmation_before_remember_me_is_reset` |
| `test_confirming_for_a_user_without_stored_sessions_resets_remember_me_only` |
| `test_force_skips_the_confirmation_for_a_user_without_stored_sessions` |
| `test_revocation_signs_out_a_logged_in_browser` |
| `test_it_fails_safely_for_a_non_database_session_driver` |
| `test_it_fails_without_claiming_success_when_counting_sessions_fails` |
| `test_it_fails_without_claiming_success_when_deleting_sessions_fails` |
| `test_the_largest_integer_id_is_a_valid_selector_for_a_missing_user` |
| `test_a_zero_padded_id_selects_the_same_user` |
| `test_an_email_stored_with_mixed_case_is_found_regardless_of_the_selector_case` |
| `test_an_email_matching_several_users_is_refused_without_revoking_anything` |
| `test_revocation_also_invalidates_remember_me_cookies` |
| `test_remember_me_cookies_are_invalidated_even_when_no_session_row_is_stored` |
| `test_declining_the_confirmation_keeps_remember_me_cookies_valid` |
| `test_a_failure_to_reset_the_remember_token_is_reported_as_a_failure` |
| `test_a_declined_token_update_is_reported_as_a_failure` |
| `test_a_declined_token_update_is_reported_as_a_failure_with_no_stored_sessions` |
| `test_a_failure_to_delete_sessions_reports_that_remember_me_logins_were_already_reset` |

## Feature/Notifications/SecurityEventNotificationTest

[Feature/Notifications/SecurityEventNotificationTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Notifications/SecurityEventNotificationTest.php) — 37 methods

| Test key |
| --- |
| `test_password_change_notifies_the_user_once` |
| `test_password_change_with_wrong_current_password_does_not_notify` |
| `test_password_change_succeeds_even_when_notification_fails` |
| `test_password_change_with_cancelled_save_does_not_notify` |
| `test_password_reset_notifies_the_user_once` |
| `test_password_reset_with_invalid_token_does_not_notify` |
| `test_password_reset_with_invalid_password_does_not_notify` |
| `test_password_reset_with_cancelled_save_does_not_notify` |
| `test_requesting_a_password_reset_link_does_not_notify_as_a_reset` |
| `test_two_factor_with_confirmation_notifies_only_once_confirmed` |
| `test_two_factor_confirmation_that_is_not_saved_does_not_notify` |
| `test_two_factor_without_confirmation_notifies_once_on_enable` |
| `test_disabling_two_factor_enabled_without_confirmation_notifies_the_user_once` |
| `test_disabling_two_factor_notifies_the_user_once` |
| `test_disabling_two_factor_that_was_never_observed_being_enabled_still_notifies` |
| `test_disabling_two_factor_that_was_never_enabled_does_not_notify` |
| `test_disabling_an_unconfirmed_two_factor_setup_does_not_notify` |
| `test_reconfirming_two_factor_does_not_notify_again` |
| `test_reconfirming_two_factor_from_a_separate_request_does_not_notify_again` |
| `test_regenerating_recovery_codes_notifies_the_user_once` |
| `test_regenerating_recovery_codes_with_cancelled_save_does_not_notify` |
| `test_deleting_a_passkey_notifies_the_user_once` |
| `test_deleting_another_users_passkey_is_forbidden_and_does_not_notify` |
| `test_registering_an_invalid_passkey_does_not_notify` |
| `test_a_stored_passkey_registration_notifies_the_user_once` |
| `test_a_passkey_registration_event_for_an_unstored_passkey_does_not_notify` |
| `test_email_change_notifies_only_the_old_address_once` |
| `test_email_change_with_wrong_password_does_not_notify` |
| `test_email_change_to_a_taken_address_does_not_notify` |
| `test_name_only_change_does_not_notify` |
| `test_email_change_succeeds_even_when_notification_fails` |
| `test_email_change_with_cancelled_save_does_not_notify` |
| `test_account_deletion_notifies_the_deleted_address_once` |
| `test_account_deletion_with_wrong_password_does_not_notify` |
| `test_account_deletion_succeeds_even_when_notification_fails` |
| `test_account_deletion_with_cancelled_delete_does_not_notify` |
| `test_no_notification_mail_contains_links_tokens_or_the_new_address` |

## Feature/NotificationTest

[Feature/NotificationTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/NotificationTest.php) — 14 methods

| Test key |
| --- |
| `test_a_notification_can_be_stored_for_a_user` |
| `test_an_insecure_action_url_is_rejected` |
| `test_a_user_can_mark_their_notification_as_read` |
| `test_a_user_cannot_read_another_users_notification` |
| `test_a_user_can_delete_their_notification` |
| `test_a_user_cannot_delete_another_users_notification` |
| `test_an_alert_is_sent_to_the_configured_mail_and_slack_routes` |
| `test_an_alert_skips_unconfigured_channels` |
| `test_an_alert_rejects_an_insecure_action_url` |
| `test_the_alert_command_sends_to_requested_channels` |
| `test_the_alert_command_fails_without_recipients` |
| `test_reported_exceptions_trigger_a_throttled_alert` |
| `test_an_alert_is_still_sent_when_the_cache_is_unavailable` |
| `test_the_exception_command_sends_through_the_throwable_alert_path` |

## Feature/SecurityEventLoggingTest

[Feature/SecurityEventLoggingTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/SecurityEventLoggingTest.php) — 16 methods

| Test key |
| --- |
| `test_changing_the_password_records_a_security_event` |
| `test_a_wrong_current_password_records_a_failed_password_change` |
| `test_a_cancelled_password_update_is_not_recorded_as_a_change` |
| `test_resetting_the_password_records_a_security_event_for_the_user` |
| `test_an_invalid_reset_token_records_a_failed_password_reset` |
| `test_a_failing_account_lookup_does_not_change_the_failed_reset_response` |
| `test_a_reset_for_an_unknown_account_records_a_failure_without_a_subject` |
| `test_a_cancelled_password_reset_is_not_recorded` |
| `test_changing_the_email_records_a_security_event_without_email_values` |
| `test_a_wrong_current_password_records_a_failed_email_change` |
| `test_updating_only_the_name_does_not_record_an_email_change` |
| `test_a_cancelled_profile_update_is_not_recorded_as_an_email_change` |
| `test_deleting_the_account_records_the_user_id_without_a_subject` |
| `test_a_wrong_password_does_not_delete_the_account_and_records_the_failure` |
| `test_a_cancelled_account_deletion_is_not_recorded` |
| `test_a_logging_failure_does_not_undo_the_password_change` |

## Feature/AuthenticationFailureLoggingTest

[Feature/AuthenticationFailureLoggingTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/AuthenticationFailureLoggingTest.php) — 5 methods

| Test key |
| --- |
| `test_a_wrong_password_records_a_failed_login_for_the_account` |
| `test_an_unknown_account_records_a_failed_login_without_a_subject` |
| `test_a_successful_login_does_not_record_a_failure` |
| `test_a_wrong_two_factor_code_records_a_failed_mfa_attempt` |
| `test_a_wrong_recovery_code_records_the_recovery_code_method` |

## Feature/MfaSecurityEventLoggingTest

[Feature/MfaSecurityEventLoggingTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/MfaSecurityEventLoggingTest.php) — 11 methods

| Test key |
| --- |
| `test_starting_two_factor_setup_is_not_recorded_until_it_is_confirmed` |
| `test_confirming_two_factor_records_mfa_enabled` |
| `test_disabling_confirmed_two_factor_records_mfa_disabled_as_active` |
| `test_cancelling_an_unconfirmed_setup_is_recorded_as_not_active` |
| `test_regenerating_recovery_codes_is_recorded_without_the_codes` |
| `test_a_wrong_confirmation_code_does_not_record_mfa_enabled` |
| `test_cancelled_user_saves_do_not_record_mfa_events` |
| `test_a_cancelled_confirmation_save_does_not_record_mfa_enabled` |
| `test_confirming_an_already_confirmed_two_factor_is_not_recorded_again` |
| `test_reconfirming_two_factor_on_the_same_instance_at_the_same_instant_is_not_recorded_again` |
| `test_a_cancelled_save_after_a_successful_one_on_the_same_instance_is_not_recorded` |

## Feature/PasskeySecurityEventLoggingTest

[Feature/PasskeySecurityEventLoggingTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/PasskeySecurityEventLoggingTest.php) — 5 methods

| Test key |
| --- |
| `test_a_created_passkey_is_recorded_without_its_name` |
| `test_a_passkey_that_was_not_created_is_not_recorded` |
| `test_deleting_a_passkey_records_the_event` |
| `test_a_cancelled_passkey_delete_is_not_recorded` |
| `test_deleting_another_users_passkey_is_forbidden_and_not_recorded` |

## Feature/SecurityEventAccessTest

[Feature/SecurityEventAccessTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/SecurityEventAccessTest.php) — 13 methods

| Test key |
| --- |
| `test_guests_are_redirected_to_the_login_page` |
| `test_a_user_who_is_not_a_viewer_is_forbidden` |
| `test_nobody_can_view_when_no_viewers_are_configured` |
| `test_a_viewer_sees_only_security_events_newest_first` |
| `test_the_viewer_email_is_matched_case_insensitively` |
| `test_a_viewer_with_an_unverified_email_is_redirected_to_verification` |
| `test_events_can_be_filtered_by_result` |
| `test_events_can_be_filtered_by_event_name` |
| `test_security_events_cannot_be_changed_over_http` |
| `test_the_gate_denies_a_listed_email_that_is_not_verified` |
| `test_the_gate_allows_a_listed_email_that_is_verified` |
| `test_a_listed_email_cannot_view_when_the_user_has_no_email_verification` |
| `test_the_gate_denies_a_listed_email_when_the_user_has_no_email_verification` |

## Feature/SecurityEventImmutabilityTest

[Feature/SecurityEventImmutabilityTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/SecurityEventImmutabilityTest.php) — 8 methods

| Test key |
| --- |
| `test_a_security_event_cannot_be_updated` |
| `test_a_security_event_cannot_be_deleted` |
| `test_renaming_the_log_does_not_bypass_the_update_guard` |
| `test_renaming_the_log_does_not_bypass_the_delete_guard` |
| `test_a_record_loaded_from_the_database_is_guarded_after_renaming_the_log` |
| `test_deleting_the_subject_user_keeps_its_security_events` |
| `test_the_retention_cleanup_still_deletes_expired_security_events` |
| `test_other_logs_are_not_affected_by_the_guard` |

## Feature/ActivityLogRetentionTest

[Feature/ActivityLogRetentionTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/ActivityLogRetentionTest.php) — 3 methods

| Test key |
| --- |
| `test_activity_log_cleanup_is_scheduled_daily_per_log_with_configured_days` |
| `test_cleaning_the_security_log_only_deletes_old_security_records` |
| `test_invalid_retention_days_do_not_schedule_cleanup` |

## Unit/FortifyServiceProviderChiselTest

[Unit/FortifyServiceProviderChiselTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Unit/FortifyServiceProviderChiselTest.php) — 1 methods

| Test key |
| --- |
| `test_disabling_registration_keeps_reset_user_password_resolvable` |

## Feature/ExampleTest

[Feature/ExampleTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/ExampleTest.php) — 1 methods

| Test key |
| --- |
| `test_returns_a_successful_response` |

## Unit/ExampleTest

[Unit/ExampleTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Unit/ExampleTest.php) — 1 methods

| Test key |
| --- |
| `test_that_true_is_true` |


</div>
