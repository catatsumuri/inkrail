---
title: テスト項目一覧
description: コミット済みの240テストメソッドと日本語の確認内容。
---

<div class="test-inventory">

コミット済みのテストをファイルごとにまとめた一覧です。**25ファイル・240項目**（Feature 238項目、Unit 2項目）を掲載しています。英語のキーはテストメソッド名をそのまま残し、日本語の確認内容を併記します。

項目数はテストメソッド数です。データプロバイダーを使う項目は複数の入力で実行されるため、テスト実行時のケース数とは異なります。この一覧は実装された検査内容の説明であり、最新の実行結果や、すべての構成での動作保証を示すものではありません。

実行方法は[テストと静的解析](../#テストと静的解析)を参照してください。

## ログイン・ログアウト

[Feature/Auth/AuthenticationTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Auth/AuthenticationTest.php) — 6項目

| テストキー | 確認内容 |
| --- | --- |
| `test_login_screen_can_be_rendered` | ログイン画面を表示できる |
| `test_users_can_authenticate_using_the_login_screen` | ログイン画面から認証できる |
| `test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge` | 二要素認証が有効なユーザーを追加認証へ誘導する |
| `test_users_can_not_authenticate_with_invalid_password` | 誤ったパスワードでは認証できない |
| `test_users_can_logout` | ログアウトできる |
| `test_users_are_rate_limited` | ログイン試行にレート制限が適用される |

## ユーザー登録

[Feature/Auth/RegistrationTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Auth/RegistrationTest.php) — 2項目

| テストキー | 確認内容 |
| --- | --- |
| `test_registration_screen_can_be_rendered` | 登録画面を表示できる |
| `test_new_users_can_register` | 新規ユーザーを登録できる |

## メールアドレスの確認

[Feature/Auth/EmailVerificationTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Auth/EmailVerificationTest.php) — 7項目

| テストキー | 確認内容 |
| --- | --- |
| `test_email_verification_screen_can_be_rendered` | メール確認画面を表示できる |
| `test_unverified_users_are_redirected_to_the_email_verification_prompt` | 未確認ユーザーをメール確認画面へ誘導する |
| `test_email_can_be_verified` | メールアドレスを確認済みにできる |
| `test_email_is_not_verified_with_invalid_hash` | 不正なハッシュでは確認済みにならない |
| `test_email_is_not_verified_with_invalid_user_id` | 不正なユーザーIDでは確認済みにならない |
| `test_verified_user_is_redirected_to_dashboard_from_verification_prompt` | 確認済みユーザーは確認画面からダッシュボードへ移動する |
| `test_already_verified_user_visiting_verification_link_is_redirected_without_firing_event_again` | 確認済みユーザーが確認リンクを再訪してもイベントを重複発火しない |

## メール確認通知

[Feature/Auth/VerificationNotificationTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Auth/VerificationNotificationTest.php) — 2項目

| テストキー | 確認内容 |
| --- | --- |
| `test_sends_verification_notification` | メール確認通知を送信する |
| `test_does_not_send_verification_notification_if_email_is_verified` | 確認済みのメールアドレスには確認通知を送らない |

## パスワードによる本人確認

[Feature/Auth/PasswordConfirmationTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Auth/PasswordConfirmationTest.php) — 2項目

| テストキー | 確認内容 |
| --- | --- |
| `test_confirm_password_screen_can_be_rendered` | パスワード確認画面を表示できる |
| `test_password_confirmation_requires_authentication` | パスワード確認にはログインが必要 |

## パスワードリセット

[Feature/Auth/PasswordResetTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Auth/PasswordResetTest.php) — 5項目

| テストキー | 確認内容 |
| --- | --- |
| `test_reset_password_link_screen_can_be_rendered` | リセットリンクの申請画面を表示できる |
| `test_reset_password_link_can_be_requested` | リセットリンクを申請できる |
| `test_reset_password_screen_can_be_rendered` | パスワードリセット画面を表示できる |
| `test_password_can_be_reset_with_valid_token` | 有効なトークンでパスワードをリセットできる |
| `test_password_cannot_be_reset_with_invalid_token` | 無効なトークンではパスワードをリセットできない |

## パスワードリセットのレート制限

[Feature/Auth/PasswordResetThrottleTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Auth/PasswordResetThrottleTest.php) — 5項目

| テストキー | 確認内容 |
| --- | --- |
| `test_failed_resets_within_the_limit_are_all_recorded` | 制限内のリセット失敗をすべて記録する |
| `test_repeated_attempts_for_one_account_are_throttled_without_more_log_rows` | 同一アカウントへの連続試行を制限し、追加のログを記録しない |
| `test_one_address_cannot_flood_the_log_by_varying_the_email` | 同一IPからメールアドレスを変えても大量のログを作れない |
| `test_a_throttled_account_does_not_block_other_accounts` | あるアカウントの制限が別のアカウントを妨げない |
| `test_requesting_a_reset_link_is_not_affected_by_the_reset_limit` | リセット処理の制限がリンクの申請に影響しない |

## 二要素認証の追加確認

[Feature/Auth/TwoFactorChallengeTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Auth/TwoFactorChallengeTest.php) — 2項目

| テストキー | 確認内容 |
| --- | --- |
| `test_two_factor_challenge_redirects_to_login_when_not_authenticated` | 認証前の状態がない場合はログインへ戻す |
| `test_two_factor_challenge_can_be_rendered` | 二要素認証の確認画面を表示できる |

## ダッシュボード

[Feature/DashboardTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/DashboardTest.php) — 2項目

| テストキー | 確認内容 |
| --- | --- |
| `test_guests_are_redirected_to_the_login_page` | 未ログインの利用者をログイン画面へ誘導する |
| `test_authenticated_users_can_visit_the_dashboard` | ログイン済みユーザーはダッシュボードを閲覧できる |

## プロフィールとアカウント削除

[Feature/Settings/ProfileUpdateTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Settings/ProfileUpdateTest.php) — 15項目

| テストキー | 確認内容 |
| --- | --- |
| `test_profile_page_is_displayed` | プロフィール画面を表示できる |
| `test_profile_information_can_be_updated` | プロフィール情報を更新できる |
| `test_email_cannot_be_changed_without_the_current_password` | 現在のパスワードなしではメールアドレスを変更できない |
| `test_email_cannot_be_changed_with_an_incorrect_password` | 現在のパスワードが誤っているとメールアドレスを変更できない |
| `test_current_password_cannot_be_brute_forced_through_the_profile_update` | プロフィール更新経由のパスワード総当たりを制限する |
| `test_profile_update_throttling_does_not_affect_other_users` | プロフィール更新の制限が別ユーザーに影響しない |
| `test_email_cannot_be_changed_by_letter_case_without_the_current_password` | 大文字・小文字だけのメール変更にも現在のパスワードが必要 |
| `test_array_email_is_rejected_with_a_validation_error` | 配列のメールアドレスを入力エラーとして拒否する |
| `test_empty_email_is_rejected_with_a_validation_error` | 空のメールアドレスを入力エラーとして拒否する |
| `test_array_current_password_is_rejected_with_a_validation_error` | 配列の現在のパスワードを入力エラーとして拒否する |
| `test_email_is_not_changed_by_extra_request_fields` | 余分なリクエスト項目でメールアドレスが変更されない |
| `test_name_can_be_changed_without_the_current_password` | 名前だけなら現在のパスワードなしで変更できる |
| `test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged` | メールアドレスが同じなら確認済み状態を維持する |
| `test_user_can_delete_their_account` | 自分のアカウントを削除できる |
| `test_correct_password_must_be_provided_to_delete_account` | アカウント削除には正しいパスワードが必要 |

## セキュリティ設定

[Feature/Settings/SecurityTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Settings/SecurityTest.php) — 5項目

| テストキー | 確認内容 |
| --- | --- |
| `test_security_page_is_displayed` | セキュリティ画面を表示できる |
| `test_security_page_requires_password_confirmation_when_enabled` | 本人確認が有効な構成ではパスワード確認を要求する |
| `test_security_page_renders_without_two_factor_when_feature_is_disabled` | 二要素認証を無効にした構成でも画面を表示できる |
| `test_password_can_be_updated` | パスワードを更新できる |
| `test_correct_password_must_be_provided_to_update_password` | パスワード更新には正しい現在のパスワードが必要 |

## ブラウザーからのセッション管理

[Feature/Settings/SessionManagementTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Settings/SessionManagementTest.php) — 49項目

| テストキー | 確認内容 |
| --- | --- |
| `test_user_can_view_their_current_and_other_active_sessions` | 現在とほかの有効なセッションを閲覧できる |
| `test_guests_cannot_access_session_management` | 未ログインではセッション管理にアクセスできない |
| `test_the_session_page_itself_requires_recent_password_confirmation` | 一覧画面自体に直近のパスワード確認が必要 |
| `test_confirming_the_password_returns_to_the_page_and_revocation_then_needs_no_second_prompt` | 本人確認後に元の画面へ戻り、失効時に再確認を求めない |
| `test_viewing_the_session_page_is_not_throttled_like_revocation` | 一覧の閲覧には失効操作と同じレート制限を適用しない |
| `test_session_presenter_marks_the_current_session_and_masks_its_details` | 現在のセッションを識別し、詳細をマスクする |
| `test_session_page_is_safe_when_database_sessions_are_not_available` | databaseセッションが使えない場合も安全に画面を表示する |
| `test_session_page_is_safe_when_session_storage_is_inaccessible` | セッション保存先にアクセスできない場合も安全に画面を表示する |
| `test_other_sessions_are_paginated` | ほかのセッションをページ分割する |
| `test_user_can_revoke_another_session` | ほかのセッションを1件失効できる |
| `test_user_cannot_revoke_another_users_session` | 別ユーザーのセッションを失効できない |
| `test_user_can_revoke_all_other_sessions` | 自分以外のセッションをすべて失効できる |
| `test_only_active_sessions_can_be_revoked_individually` | 個別失効は有効なセッションだけを対象にする |
| `test_revoking_other_sessions_preserves_the_supplied_current_session` | ほかのセッションの失効では指定された現在のセッションを維持する |
| `test_deleting_a_database_row_invalidates_every_copy_of_the_session_id` | データベース行の削除で同じセッションIDの全コピーを無効化する |
| `test_user_can_revoke_all_sessions_including_the_current_session` | 現在のセッションを含めてすべて失効できる |
| `test_revocation_is_unavailable_for_non_database_session_drivers` | database以外のドライバーでは失効できない |
| `test_revocation_requires_password_confirmation_within_fifteen_minutes` | 失効には15分以内のパスワード確認が必要 |
| `test_revocation_without_any_confirmation_is_redirected_to_confirm_password` | 本人確認が一度もない失効操作を確認画面へ誘導する |
| `test_revocation_is_allowed_within_the_confirmation_window` | 本人確認の有効時間内なら失効できる |
| `test_revocation_endpoints_are_rate_limited` | 失効エンドポイントにレート制限を適用する |
| `test_unknown_and_foreign_fingerprints_get_the_same_response` | 不明な識別子と別ユーザーの識別子に同じ応答を返す |
| `test_session_ids_are_not_exposed_to_the_frontend` | フロントエンドへセッションIDを公開しない |
| `test_storage_failure_while_revoking_reports_an_error_and_does_not_sign_the_user_out` | 失効中の保存先エラーを報告し、実行ユーザーをログアウトさせない |
| `test_current_session_cannot_be_revoked_through_the_individual_endpoint` | 個別失効エンドポイントで現在のセッションを失効できない |
| `test_revoking_another_session_signs_out_the_original_and_copied_cookie` | 個別失効で元のCookieとコピーしたCookieの両方をログアウトさせる |
| `test_revoking_other_sessions_signs_out_every_other_client_but_keeps_the_caller` | ほかのセッションの失効では実行ユーザーだけを維持する |
| `test_revoking_all_sessions_signs_out_every_client_including_the_caller` | 全セッションの失効では実行ユーザーを含め全クライアントをログアウトさせる |
| `test_a_revoked_client_cannot_revoke_anything` | 失効済みクライアントから失効操作できない |
| `test_revoking_other_sessions_also_invalidates_other_remember_me_cookies` | ほかのセッションの失効でRemember me Cookieも無効化する |
| `test_revoking_other_sessions_invalidates_remember_me_cookies_even_when_no_other_session_is_stored` | ほかの保存済みセッションがなくてもRemember me Cookieを無効化する |
| `test_revoking_one_session_also_ends_remember_me_logins_so_it_cannot_come_back` | 1件の失効でもRemember meによる再ログインを防ぐ |
| `test_revoking_an_unknown_or_foreign_session_does_not_touch_the_remember_token` | 不明または別ユーザーのセッションへの操作でRemember meトークンを変えない |
| `test_failing_to_reset_the_remember_token_keeps_the_session_when_revoking_one` | 個別失効でRemember meトークンのリセットに失敗したらセッションを維持する |
| `test_a_failing_delete_while_revoking_one_session_changes_nothing` | 個別失効の削除失敗では変更を残さない |
| `test_a_failing_token_update_rolls_back_the_deleted_session_when_revoking_one` | 個別失効のトークン更新失敗では削除したセッションを復元する |
| `test_a_declined_token_update_rolls_back_the_deleted_session_when_revoking_one` | 個別失効のトークン更新拒否では削除したセッションを復元する |
| `test_a_session_that_vanishes_before_deletion_does_not_reset_the_remember_token` | 削除前に対象が消えた場合はRemember meトークンをリセットしない |
| `test_a_failing_delete_while_revoking_one_session_reports_a_partial_result_on_a_separate_connection` | 別接続での個別削除失敗は一部完了として報告する |
| `test_a_session_that_vanishes_before_deletion_is_reported_as_partial_on_a_separate_connection` | 別接続で削除前に対象が消えた場合は一部完了として報告する |
| `test_revocation_works_with_sessions_stored_on_an_independent_database_connection` | 独立したデータベース接続のセッションも失効できる |
| `test_a_failing_delete_while_revoking_other_sessions_changes_nothing` | ほかのセッションの削除失敗では変更を残さない |
| `test_a_failing_delete_while_revoking_other_sessions_reports_a_partial_result_on_a_separate_connection` | 別接続でのほかのセッションの削除失敗は一部完了として報告する |
| `test_a_failing_delete_while_revoking_all_sessions_changes_nothing_and_keeps_the_user_signed_in` | 全セッションの削除失敗では変更を残さずログインを維持する |
| `test_a_failing_delete_while_revoking_all_sessions_reports_a_partial_result_on_a_separate_connection` | 別接続での全セッションの削除失敗は一部完了として報告する |
| `test_a_failing_token_update_rolls_back_the_deleted_sessions_when_revoking_others_and_all` | 一括失効のトークン更新失敗では削除したセッションを復元する |
| `test_a_declined_token_update_rolls_back_the_deleted_sessions_when_revoking_others_and_all` | 一括失効のトークン更新拒否では削除したセッションを復元する |
| `test_revoking_all_sessions_also_invalidates_remember_me_cookies` | 全セッションの失効でRemember me Cookieも無効化する |
| `test_failing_to_reset_the_remember_token_does_not_report_success_or_sign_the_user_out` | Remember meトークンのリセット失敗を成功扱いせずログインを維持する |

## CLIからのセッション失効

[Feature/Console/RevokeUserSessionsCommandTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Console/RevokeUserSessionsCommandTest.php) — 23項目

| テストキー | 確認内容 |
| --- | --- |
| `test_a_selector_must_state_its_type_and_a_valid_value` | 対象指定には種類と有効な値が必要 |
| `test_an_unknown_user_fails_without_touching_other_sessions` | 不明なユーザーではほかのセッションに触れず失敗する |
| `test_declining_the_confirmation_preserves_every_session` | 確認を拒否すると全セッションを維持する |
| `test_confirming_revokes_every_session_of_the_user` | 確認を承認すると対象ユーザーの全セッションを失効する |
| `test_force_revokes_only_the_target_users_sessions_including_expired_rows` | force指定は期限切れを含む対象ユーザーのセッションだけを失効する |
| `test_a_user_without_stored_sessions_still_requires_confirmation_before_remember_me_is_reset` | 保存済みセッションがなくてもRemember meリセット前に確認する |
| `test_confirming_for_a_user_without_stored_sessions_resets_remember_me_only` | 保存済みセッションがない場合は承認後にRemember meだけをリセットする |
| `test_force_skips_the_confirmation_for_a_user_without_stored_sessions` | 保存済みセッションがなくてもforce指定で確認を省略できる |
| `test_revocation_signs_out_a_logged_in_browser` | 失効でログイン中のブラウザーをログアウトさせる |
| `test_it_fails_safely_for_a_non_database_session_driver` | database以外のドライバーでは安全に失敗する |
| `test_it_fails_without_claiming_success_when_counting_sessions_fails` | セッション件数の取得失敗を成功扱いしない |
| `test_it_fails_without_claiming_success_when_deleting_sessions_fails` | セッション削除失敗を成功扱いしない |
| `test_the_largest_integer_id_is_a_valid_selector_for_a_missing_user` | 整数の最大値を有効な形式のIDとして受け付け、対象なしを判定する |
| `test_a_zero_padded_id_selects_the_same_user` | ゼロ埋めされたIDでも同じユーザーを選ぶ |
| `test_an_email_stored_with_mixed_case_is_found_regardless_of_the_selector_case` | 保存済みメールアドレスと指定値の大文字・小文字を区別しない |
| `test_an_email_matching_several_users_is_refused_without_revoking_anything` | 複数ユーザーに一致するメールアドレスを拒否し、失効しない |
| `test_revocation_also_invalidates_remember_me_cookies` | 失効でRemember me Cookieも無効化する |
| `test_remember_me_cookies_are_invalidated_even_when_no_session_row_is_stored` | セッション行がなくてもRemember me Cookieを無効化する |
| `test_declining_the_confirmation_keeps_remember_me_cookies_valid` | 確認を拒否するとRemember me Cookieの有効性を維持する |
| `test_a_failure_to_reset_the_remember_token_is_reported_as_a_failure` | Remember meトークンのリセット失敗を失敗として報告する |
| `test_a_declined_token_update_is_reported_as_a_failure` | トークン更新の拒否を失敗として報告する |
| `test_a_declined_token_update_is_reported_as_a_failure_with_no_stored_sessions` | 保存済みセッションがない場合もトークン更新の拒否を失敗として報告する |
| `test_a_failure_to_delete_sessions_reports_that_remember_me_logins_were_already_reset` | セッション削除失敗時にRemember meがリセット済みであることを報告する |

## アカウント変更通知

[Feature/Notifications/SecurityEventNotificationTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/Notifications/SecurityEventNotificationTest.php) — 37項目

| テストキー | 確認内容 |
| --- | --- |
| `test_password_change_notifies_the_user_once` | パスワード変更をユーザーへ1回だけ通知する |
| `test_password_change_with_wrong_current_password_does_not_notify` | 現在のパスワードが誤っている変更は通知しない |
| `test_password_change_succeeds_even_when_notification_fails` | 通知失敗でもパスワード変更は成功する |
| `test_password_change_with_cancelled_save_does_not_notify` | パスワード変更の保存が中止されたら通知しない |
| `test_password_reset_notifies_the_user_once` | パスワードリセットをユーザーへ1回だけ通知する |
| `test_password_reset_with_invalid_token_does_not_notify` | 無効なトークンのリセットは通知しない |
| `test_password_reset_with_invalid_password_does_not_notify` | 無効な新パスワードのリセットは通知しない |
| `test_password_reset_with_cancelled_save_does_not_notify` | リセットの保存が中止されたら通知しない |
| `test_requesting_a_password_reset_link_does_not_notify_as_a_reset` | リセットリンクの申請をリセット完了として通知しない |
| `test_two_factor_with_confirmation_notifies_only_once_confirmed` | 確認が必要な二要素認証は確認完了後だけ通知する |
| `test_two_factor_confirmation_that_is_not_saved_does_not_notify` | 二要素認証の確認結果が保存されない場合は通知しない |
| `test_two_factor_without_confirmation_notifies_once_on_enable` | 確認不要の構成では二要素認証の有効化時に1回通知する |
| `test_disabling_two_factor_enabled_without_confirmation_notifies_the_user_once` | 確認不要で有効化した二要素認証の無効化を1回通知する |
| `test_disabling_two_factor_notifies_the_user_once` | 二要素認証の無効化を1回通知する |
| `test_disabling_two_factor_that_was_never_observed_being_enabled_still_notifies` | 有効化を同じ処理内で観測していなくても有効な二要素認証の無効化を通知する |
| `test_disabling_two_factor_that_was_never_enabled_does_not_notify` | 一度も有効になっていない二要素認証の無効化は通知しない |
| `test_disabling_an_unconfirmed_two_factor_setup_does_not_notify` | 未確認の二要素認証設定の取り消しは通知しない |
| `test_reconfirming_two_factor_does_not_notify_again` | 二要素認証の再確認で重複通知しない |
| `test_reconfirming_two_factor_from_a_separate_request_does_not_notify_again` | 別リクエストからの再確認でも重複通知しない |
| `test_regenerating_recovery_codes_notifies_the_user_once` | リカバリーコードの再生成を1回通知する |
| `test_regenerating_recovery_codes_with_cancelled_save_does_not_notify` | リカバリーコードの保存が中止されたら通知しない |
| `test_deleting_a_passkey_notifies_the_user_once` | パスキー削除を1回通知する |
| `test_deleting_another_users_passkey_is_forbidden_and_does_not_notify` | 別ユーザーのパスキー削除を拒否し、通知しない |
| `test_registering_an_invalid_passkey_does_not_notify` | 無効なパスキーの登録は通知しない |
| `test_a_stored_passkey_registration_notifies_the_user_once` | 保存されたパスキー登録を1回通知する |
| `test_a_passkey_registration_event_for_an_unstored_passkey_does_not_notify` | 保存されていないパスキーの登録イベントでは通知しない |
| `test_email_change_notifies_only_the_old_address_once` | メール変更を変更前のアドレスだけへ1回通知する |
| `test_email_change_with_wrong_password_does_not_notify` | パスワードが誤っているメール変更は通知しない |
| `test_email_change_to_a_taken_address_does_not_notify` | 使用済みアドレスへのメール変更は通知しない |
| `test_name_only_change_does_not_notify` | 名前だけの変更は通知しない |
| `test_email_change_succeeds_even_when_notification_fails` | 通知失敗でもメール変更は成功する |
| `test_email_change_with_cancelled_save_does_not_notify` | メール変更の保存が中止されたら通知しない |
| `test_account_deletion_notifies_the_deleted_address_once` | アカウント削除を削除時点のアドレスへ1回通知する |
| `test_account_deletion_with_wrong_password_does_not_notify` | パスワードが誤っているアカウント削除は通知しない |
| `test_account_deletion_succeeds_even_when_notification_fails` | 通知失敗でもアカウント削除は成功する |
| `test_account_deletion_with_cancelled_delete_does_not_notify` | アカウント削除が中止されたら通知しない |
| `test_no_notification_mail_contains_links_tokens_or_the_new_address` | 通知メールにリンク・トークン・変更後のアドレスを含めない |

## 汎用通知と運用アラート

[Feature/NotificationTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/NotificationTest.php) — 14項目

| テストキー | 確認内容 |
| --- | --- |
| `test_a_notification_can_be_stored_for_a_user` | ユーザーの通知を保存できる |
| `test_an_insecure_action_url_is_rejected` | 安全でない遷移先URLを拒否する |
| `test_a_user_can_mark_their_notification_as_read` | 自分の通知を既読にできる |
| `test_a_user_cannot_read_another_users_notification` | 別ユーザーの通知を既読にできない |
| `test_a_user_can_delete_their_notification` | 自分の通知を削除できる |
| `test_a_user_cannot_delete_another_users_notification` | 別ユーザーの通知を削除できない |
| `test_an_alert_is_sent_to_the_configured_mail_and_slack_routes` | 設定したメール・Slack宛先へアラートを送る |
| `test_an_alert_skips_unconfigured_channels` | 未設定の配信先をスキップする |
| `test_an_alert_rejects_an_insecure_action_url` | アラートでも安全でない遷移先URLを拒否する |
| `test_the_alert_command_sends_to_requested_channels` | アラートコマンドが指定した配信先へ送信する |
| `test_the_alert_command_fails_without_recipients` | 宛先未設定ではアラートコマンドが失敗する |
| `test_reported_exceptions_trigger_a_throttled_alert` | 報告された例外が重複抑制付きアラートを発生させる |
| `test_an_alert_is_still_sent_when_the_cache_is_unavailable` | キャッシュが利用できなくてもアラート送信を試みる |
| `test_the_exception_command_sends_through_the_throwable_alert_path` | 例外テストコマンドが例外アラートと同じ経路で送信する |

## アカウント変更のイベント記録

[Feature/SecurityEventLoggingTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/SecurityEventLoggingTest.php) — 16項目

| テストキー | 確認内容 |
| --- | --- |
| `test_changing_the_password_records_a_security_event` | パスワード変更を記録する |
| `test_a_wrong_current_password_records_a_failed_password_change` | 現在のパスワードの誤りを変更失敗として記録する |
| `test_a_cancelled_password_update_is_not_recorded_as_a_change` | 中止されたパスワード更新を変更として記録しない |
| `test_resetting_the_password_records_a_security_event_for_the_user` | パスワードリセットを対象ユーザーのイベントとして記録する |
| `test_an_invalid_reset_token_records_a_failed_password_reset` | 無効なリセットトークンを失敗として記録する |
| `test_a_failing_account_lookup_does_not_change_the_failed_reset_response` | アカウント検索失敗でリセット失敗の応答が変わらない |
| `test_a_reset_for_an_unknown_account_records_a_failure_without_a_subject` | 不明なアカウントのリセット失敗は対象ユーザーなしで記録する |
| `test_a_cancelled_password_reset_is_not_recorded` | 中止されたパスワードリセットを記録しない |
| `test_changing_the_email_records_a_security_event_without_email_values` | メール変更をアドレスの値を含めず記録する |
| `test_a_wrong_current_password_records_a_failed_email_change` | 現在のパスワードの誤りをメール変更失敗として記録する |
| `test_updating_only_the_name_does_not_record_an_email_change` | 名前だけの更新をメール変更として記録しない |
| `test_a_cancelled_profile_update_is_not_recorded_as_an_email_change` | 中止されたプロフィール更新をメール変更として記録しない |
| `test_deleting_the_account_records_the_user_id_without_a_subject` | アカウント削除では対象モデルなしでユーザーIDを記録する |
| `test_a_wrong_password_does_not_delete_the_account_and_records_the_failure` | 誤ったパスワードでは削除せず、失敗を記録する |
| `test_a_cancelled_account_deletion_is_not_recorded` | 中止されたアカウント削除を記録しない |
| `test_a_logging_failure_does_not_undo_the_password_change` | ログ記録失敗でパスワード変更を取り消さない |

## 認証失敗のイベント記録

[Feature/AuthenticationFailureLoggingTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/AuthenticationFailureLoggingTest.php) — 5項目

| テストキー | 確認内容 |
| --- | --- |
| `test_a_wrong_password_records_a_failed_login_for_the_account` | 誤ったパスワードのログイン失敗を対象アカウントに紐づけて記録する |
| `test_an_unknown_account_records_a_failed_login_without_a_subject` | 不明なアカウントのログイン失敗は対象ユーザーなしで記録する |
| `test_a_successful_login_does_not_record_a_failure` | ログイン成功を失敗として記録しない |
| `test_a_wrong_two_factor_code_records_a_failed_mfa_attempt` | 誤った二要素認証コードをMFA失敗として記録する |
| `test_a_wrong_recovery_code_records_the_recovery_code_method` | 誤ったリカバリーコードでは方式をrecovery_codeとして記録する |

## 二要素認証のイベント記録

[Feature/MfaSecurityEventLoggingTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/MfaSecurityEventLoggingTest.php) — 11項目

| テストキー | 確認内容 |
| --- | --- |
| `test_starting_two_factor_setup_is_not_recorded_until_it_is_confirmed` | 二要素認証の設定開始だけでは有効化を記録しない |
| `test_confirming_two_factor_records_mfa_enabled` | 二要素認証の確認完了をmfa.enabledとして記録する |
| `test_disabling_confirmed_two_factor_records_mfa_disabled_as_active` | 確認済み二要素認証の無効化を有効状態からの変更として記録する |
| `test_cancelling_an_unconfirmed_setup_is_recorded_as_not_active` | 未確認設定の取り消しを有効状態ではなかったものとして記録する |
| `test_regenerating_recovery_codes_is_recorded_without_the_codes` | リカバリーコードの再生成をコード自体を含めず記録する |
| `test_a_wrong_confirmation_code_does_not_record_mfa_enabled` | 誤った確認コードで有効化を記録しない |
| `test_cancelled_user_saves_do_not_record_mfa_events` | 中止されたユーザー保存でMFAイベントを記録しない |
| `test_a_cancelled_confirmation_save_does_not_record_mfa_enabled` | 確認結果の保存が中止されたら有効化を記録しない |
| `test_confirming_an_already_confirmed_two_factor_is_not_recorded_again` | 確認済み二要素認証の再確認を重複記録しない |
| `test_reconfirming_two_factor_on_the_same_instance_at_the_same_instant_is_not_recorded_again` | 同じインスタンス・同じ時刻の再確認を重複記録しない |
| `test_a_cancelled_save_after_a_successful_one_on_the_same_instance_is_not_recorded` | 同じインスタンスの保存成功後に中止された保存を記録しない |

## パスキーのイベント記録

[Feature/PasskeySecurityEventLoggingTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/PasskeySecurityEventLoggingTest.php) — 5項目

| テストキー | 確認内容 |
| --- | --- |
| `test_a_created_passkey_is_recorded_without_its_name` | 作成したパスキーを名前を含めず記録する |
| `test_a_passkey_that_was_not_created_is_not_recorded` | 作成されなかったパスキーを記録しない |
| `test_deleting_a_passkey_records_the_event` | パスキーの削除を記録する |
| `test_a_cancelled_passkey_delete_is_not_recorded` | 中止されたパスキー削除を記録しない |
| `test_deleting_another_users_passkey_is_forbidden_and_not_recorded` | 別ユーザーのパスキー削除を拒否し、記録しない |

## セキュリティイベントの閲覧権限

[Feature/SecurityEventAccessTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/SecurityEventAccessTest.php) — 13項目

| テストキー | 確認内容 |
| --- | --- |
| `test_guests_are_redirected_to_the_login_page` | 未ログインの利用者をログイン画面へ誘導する |
| `test_a_user_who_is_not_a_viewer_is_forbidden` | 閲覧者ではないユーザーを拒否する |
| `test_nobody_can_view_when_no_viewers_are_configured` | 閲覧者未設定では誰も閲覧できない |
| `test_a_viewer_sees_only_security_events_newest_first` | 閲覧者にはsecurityログだけを新しい順で表示する |
| `test_the_viewer_email_is_matched_case_insensitively` | 閲覧者のメールアドレスを大文字・小文字を区別せず照合する |
| `test_a_viewer_with_an_unverified_email_is_redirected_to_verification` | メール未確認の閲覧者を確認画面へ誘導する |
| `test_events_can_be_filtered_by_result` | 結果でイベントを絞り込める |
| `test_events_can_be_filtered_by_event_name` | イベント名で絞り込める |
| `test_security_events_cannot_be_changed_over_http` | HTTP経由でイベントを変更できない |
| `test_the_gate_denies_a_listed_email_that_is_not_verified` | 許可リストにあってもメール未確認ならGateが拒否する |
| `test_the_gate_allows_a_listed_email_that_is_verified` | 許可リストにありメール確認済みならGateが許可する |
| `test_a_listed_email_cannot_view_when_the_user_has_no_email_verification` | メール確認機能を持たないユーザーは許可リストにあっても閲覧できない |
| `test_the_gate_denies_a_listed_email_when_the_user_has_no_email_verification` | メール確認機能を持たないユーザーをGateが拒否する |

## セキュリティイベントの変更防止

[Feature/SecurityEventImmutabilityTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/SecurityEventImmutabilityTest.php) — 8項目

| テストキー | 確認内容 |
| --- | --- |
| `test_a_security_event_cannot_be_updated` | セキュリティイベントを更新できない |
| `test_a_security_event_cannot_be_deleted` | セキュリティイベントを削除できない |
| `test_renaming_the_log_does_not_bypass_the_update_guard` | ログ名を変更しても更新の保護を回避できない |
| `test_renaming_the_log_does_not_bypass_the_delete_guard` | ログ名を変更しても削除の保護を回避できない |
| `test_a_record_loaded_from_the_database_is_guarded_after_renaming_the_log` | DBから読み込んだレコードもログ名変更後に保護される |
| `test_deleting_the_subject_user_keeps_its_security_events` | 対象ユーザーを削除してもイベントを保持する |
| `test_the_retention_cleanup_still_deletes_expired_security_events` | 保持期間の削除処理では期限切れのイベントを削除できる |
| `test_other_logs_are_not_affected_by_the_guard` | ほかのログには変更防止の保護が影響しない |

## イベントの保持期間

[Feature/ActivityLogRetentionTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/ActivityLogRetentionTest.php) — 3項目

| テストキー | 確認内容 |
| --- | --- |
| `test_activity_log_cleanup_is_scheduled_daily_per_log_with_configured_days` | ログごとに設定日数を使った毎日の削除を登録する |
| `test_cleaning_the_security_log_only_deletes_old_security_records` | securityログの削除では古いsecurityレコードだけを削除する |
| `test_invalid_retention_days_do_not_schedule_cleanup` | 不正な保持日数では削除タスクを登録しない |

## Chisel構成の互換性

[Unit/FortifyServiceProviderChiselTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Unit/FortifyServiceProviderChiselTest.php) — 1項目

| テストキー | 確認内容 |
| --- | --- |
| `test_disabling_registration_keeps_reset_user_password_resolvable` | ユーザー登録を無効にしてもResetUserPasswordを解決できる |

## 初期のHTTP応答テスト

[Feature/ExampleTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Feature/ExampleTest.php) — 1項目

| テストキー | 確認内容 |
| --- | --- |
| `test_returns_a_successful_response` | 正常なHTTP応答を返す |

## 初期の単体テスト

[Unit/ExampleTest.php](https://github.com/catatsumuri/laravel-react-secure-starter-kit/blob/main/tests/Unit/ExampleTest.php) — 1項目

| テストキー | 確認内容 |
| --- | --- |
| `test_that_true_is_true` | trueがtrueであることを確認する |


</div>
