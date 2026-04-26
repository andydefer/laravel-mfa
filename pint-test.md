# Pint Formatting Test Report
*Generated: dim. 26 avril 2026 17:43:03 WAT*


  ⨯⨯⨯⨯⨯⨯..⨯⨯⨯⨯⨯.⨯⨯⨯⨯...⨯⨯⨯⨯.⨯⨯⨯⨯⨯⨯.⨯⨯⨯⨯⨯⨯⨯..⨯⨯.

  ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────── Laravel  
    FAIL   ................................................................................................................................................. 45 files, 34 style issues  
  ⨯ config/mfa.php                                                                                                                                   no_trailing_whitespace_in_comment  
  ⨯ database/migrations/2026_01_01_000001_create_two_factor_secrets_table.php                                        class_definition, phpdoc_no_package, braces_position, phpdoc_trim  
  ⨯ rector.php                                                                                                                              fully_qualified_strict_types, concat_space  
  ⨯ src/Core/Commands/CleanupMfaCommand.php      phpdoc_no_package, no_trailing_whitespace_in_comment, phpdoc_separation, phpdoc_trim, not_operator_with_successor_space, phpdoc_align  
  ⨯ src/Core/Commands/InstallMfaCommand.php                                     phpdoc_no_package, unary_operator_spaces, phpdoc_trim, not_operator_with_successor_space, phpdoc_align  
  ⨯ src/Core/Helpers/TranslationHelper.php                                                                                                             phpdoc_separation, phpdoc_align  
  ⨯ src/Core/Services/MfaInstallerService.php phpdoc_no_package, single_quote, concat_space, phpdoc_trim, not_operator_with_successor_space, blank_line_before_statement, phpdoc_alig…  
  ⨯ src/MfaServiceProvider.php                              new_with_parentheses, no_superfluous_phpdoc_tags, blank_line_after_opening_tag, concat_space, phpdoc_trim, ordered_imports  
  ⨯ src/Otp/Contracts/CodeGeneratorInterface.php                                                                                                          blank_line_after_opening_tag  
  ⨯ src/Otp/Contracts/RateLimiterInterface.php                                                                                              blank_line_after_opening_tag, phpdoc_align  
  ⨯ src/Otp/Data/OtpResponseData.php                                                          braces_position, not_operator_with_successor_space, single_line_empty_body, phpdoc_align  
  ⨯ src/Otp/Models/OneTimePassword.php         fully_qualified_strict_types, no_superfluous_phpdoc_tags, phpdoc_trim, not_operator_with_successor_space, ordered_imports, phpdoc_align  
  ⨯ src/Otp/Notifications/OtpNotification.php                                                 braces_position, not_operator_with_successor_space, single_line_empty_body, phpdoc_align  
  ⨯ src/Otp/Services/LaravelRateLimiter.php                                                                                                                               phpdoc_align  
  ⨯ src/Otp/Services/OtpService.php             braces_position, not_operator_with_successor_space, single_line_empty_body, blank_line_before_statement, ordered_imports, phpdoc_align  
  ⨯ src/Otp/Traits/HasOneTimePasswords.php                                                              fully_qualified_strict_types, phpdoc_separation, ordered_imports, phpdoc_align  
  ⨯ src/Totp/Models/TwoFactorSecret.php                                           fully_qualified_strict_types, no_superfluous_phpdoc_tags, phpdoc_trim, ordered_imports, phpdoc_align  
  ⨯ src/Totp/Services/TOTPService.php  phpdoc_no_package, no_superfluous_phpdoc_tags, blank_line_after_opening_tag, braces_position, phpdoc_trim, single_line_empty_body, phpdoc_align  
  ⨯ src/Totp/Traits/HasTwoFactorAuthentication.php fully_qualified_strict_types, no_superfluous_phpdoc_tags, blank_line_after_opening_tag, unary_operator_spaces, phpdoc_trim, not_op…  
  ⨯ tests/Feature/Models/OneTimePasswordTest.php                                             class_attributes_separation, phpdoc_no_package, blank_line_after_opening_tag, phpdoc_trim  
  ⨯ tests/Feature/Models/TwoFactorSecretTest.php class_attributes_separation, phpdoc_no_package, fully_qualified_strict_types, blank_line_after_opening_tag, concat_space, phpdoc_tri…  
  ⨯ tests/Feature/Services/OtpServiceTest.php                                                         class_attributes_separation, new_with_parentheses, concat_space, ordered_imports  
  ⨯ tests/Feature/Services/TOTPServiceTest.php class_attributes_separation, new_with_parentheses, phpdoc_no_package, fully_qualified_strict_types, concat_space, phpdoc_trim, no_unus…  
  ⨯ tests/Feature/Traits/HasOneTimePasswordsTest.php                                                        class_attributes_separation, blank_line_after_opening_tag, ordered_imports  
  ⨯ tests/Feature/Traits/HasTwoFactorAuthenticationTest.php class_attributes_separation, new_with_parentheses, phpdoc_no_package, fully_qualified_strict_types, phpdoc_trim, ordered_…  
  ⨯ tests/Support/TestCheckPoint.php                                                                                                                    phpdoc_no_package, phpdoc_trim  
  ⨯ tests/TestCase.php                                                                                                                                      concat_space, phpdoc_align  
  ⨯ tests/Unit/Commands/CleanupMfaCommandTest.php    new_with_parentheses, phpdoc_no_package, fully_qualified_strict_types, blank_line_after_opening_tag, phpdoc_trim, ordered_imports  
  ⨯ tests/Unit/Commands/InstallMfaCommandTest.php                                                                                   new_with_parentheses, blank_line_after_opening_tag  
  ⨯ tests/Unit/MfaServiceProviderTest.php                                                            new_with_parentheses, blank_line_after_opening_tag, concat_space, ordered_imports  
  ⨯ tests/Unit/Notifications/OtpNotificationTest.php                                                                     class_attributes_separation, braces_position, ordered_imports  
  ⨯ tests/Unit/Services/DefaultCodeGeneratorTest.php                                                                              new_with_parentheses, phpdoc_no_package, phpdoc_trim  
  ⨯ tests/Unit/Services/LaravelRateLimiterTest.php                                     class_attributes_separation, new_with_parentheses, phpdoc_no_package, concat_space, phpdoc_trim  
  ⨯ tests/Unit/Services/MfaInstallerServiceTest.php                                 class_attributes_separation, new_with_parentheses, concat_space, not_operator_with_successor_space  

