/**
 * Tooltip content for AIO Login features.
 * Source: Tooltip Content for AIO Login Features.pdf
 */
export default {
	// Change WP-Admin Login URL
	changeLoginUrl: {
		content: '<p>Replace the default WordPress login URL to enhance security and prevent unauthorized access.</p>',
		helpingText: 'Enable to customize your admin login URL.',
	},

	// Limit Login Attempts
	limitLoginAttempts: {
		content: '<p>Restrict login attempts per IP to prevent brute-force attacks and block repeated failed login attempts.</p>',
		helpingText: 'Enable to restrict repeated login attempts per IP.',
	},

	// Block IP Addresses
	blockIpAddresses: {
		content: '<p>Control site access by blocking or allowing specific IP addresses using whitelist or blacklist mode.</p>',
		helpingText: 'Enable to control access using whitelist or blacklist.',
	},

	// Disable Common Usernames
	disableCommonUsernames: {
		content: '<p>Block commonly used or custom usernames to prevent brute-force attacks and strengthen login security.</p>',
		helpingText: 'Enable to prevent use of common usernames.',
	},

	// Add CAPTCHA
	captcha: {
		content: '<p>Protect your login page from spam and bots using CAPTCHAs.</p>',
		helpingText: 'Enable CAPTCHAs to block bots and spam.',
	},

	// reCAPTCHA
	recaptcha: {
		title: 'reCAPTCHA',
		content: '<p>To protect forms from bots and spam by adding Captcha.</p>',
	},

	// WooCommerce Integration
	woocommerceIntegration: {
		title: 'WooCommerce Integration',
		content: '<p>Integrate AIO Login with WooCommerce to add CAPTCHA and Social Login to login, registration, and checkout pages.</p>',
		helpingText: 'Enable secure Captcha in WooCommerce with social login features.',
	},

	// Two-Factor Authentication
	twoFactorAuth: {
		content: '<p>Require a one-time password (OTP) from an authenticator app to secure admin logins.</p>',
		helpingText: 'Enable to add OTP verification for stronger login security.',
	},
	twoFactorMasterEnable: {
		content: '<p>Master switch for 2FA on this site. When it is on, both Email OTP and Authenticator App sections appear so you and other users can configure a method. Only one site-wide method can be active at a time (use the toggles below). Each user also uses only one method at a time. When it is off, methods are hidden here and non-administrator users do not see AIO Login in wp-admin.</p>',
	},
	twoFactorEmailOtp: {
		content: '<p>Enable this to send a one-time verification code via email when users log in. This adds an extra layer of security by requiring email verification.</p>',
	},
	twoFactorTotp: {
		content: '<p>Enable Time-based One-Time Password (TOTP) authentication. Users can use apps like Google Authenticator, Authy, or Microsoft Authenticator to generate verification codes.</p>',
	},
	twoFactorRememberDevice: {
		content: '<p>When enabled, users can mark their device as trusted after a successful OTP verification. They won\'t be prompted for OTP again on that device for the configured duration. Applies to both Email OTP and TOTP.</p>',
	},

	// Temporary Access
	temporaryAccess: {
		content: '<p>Provide passwordless temporary access link for short-term tasks or guest users without compromising site security.</p>',
		helpingText: 'Enable to allow users temporary admin access securely.',
	},

	// Password Strength Checker
	passwordStrengthChecker: {
		content: '<p>Enforce custom password rules for registrations and resets to ensure strong credentials.</p>',
		helpingText: 'Enable to set rules to improve user password security.',
	},

	// User Enumeration Protection
	userEnumerationProtection: {
		content: '<p>Prevent exposure of usernames to block brute-force and phishing attacks.</p>',
		helpingText: 'Enable to hide usernames to strengthen site security.',
	},

	// Login Attempt Logs (Lockouts)
	loginAttemptLogs: {
		content: '<p>Track users who reached the maximum login attempts and were locked out to monitor security threats.</p>',
		helpingText: 'Enable to view logs of locked-out users and attempts.',
	},

	// Failed Login Attempts
	failedLoginAttempts: {
		content: '<p>Monitor and log failed login attempts to detect suspicious activity and enhance site security.</p>',
		helpingText: 'Enable to track failed logins to spot potential threats.',
	},

	// User Enumeration Logs
	userEnumerationLogs: {
		content: '<p>Track attempts to fetch usernames or user IDs to monitor unauthorized enumeration activity.</p>',
		helpingText: 'Enable to monitor username discovery attempts securely.',
	},

	// Activity Log — Notifications
	notifications: {
		title: 'Notifications',
		content: '<p>Configure alerts for security events such as lockouts, failed logins, and other activity so you can respond quickly.</p>',
		helpingText: 'Set up notification channels for important login and security events.',
	},

	// Customizer - Logo
	logo: {
		content: '<p>Upload and display a custom logo on your WordPress login page.</p>',
		helpingText: 'Add a custom logo to your login page.',
	},

	// Customizer - Background
	background: {
		content: '<p>Customize the login page background with images, colors, or slideshows.</p>',
		helpingText: 'Set background images, colors, or slideshows.',
	},

	// Customizer - Custom CSS
	customCss: {
		content: '<p>Add custom CSS to fully style your login page according to your site\'s branding.</p>',
		helpingText: 'Apply custom CSS to style the login page.',
	},

	// Customizer - Template
	templates: {
		content: '<p>Choose from pre-designed login templates to quickly apply a professional look.</p>',
		helpingText: 'Select a ready-made template for your login page.',
	},

	// Social Login (generic - e.g. WooCommerce settings section)
	socialLogin: {
		title: 'Social Login',
		content: '<p>Allow users to log in with Google, Microsoft, Facebook, Line, GitHub, or Discord. Enable on WooCommerce login, registration, and checkout pages.</p>',
	},

	// Social Login providers
	googleSocialLogin: {
		content: '<p>Allow users to log in using their Google account for faster, secure access.</p>',
		helpingText: 'Enable one-click login via Google accounts.',
	},
	microsoftSocialLogin: {
		content: '<p>Enable users to log in using their Microsoft account for fast and secure authentication.</p>',
		helpingText: 'Enable one-click login via Microsoft accounts.',
	},
	facebookSocialLogin: {
		content: '<p>Let users log in with their Facebook account for a seamless and trusted login experience.</p>',
		helpingText: 'Enable one-click login via Facebook accounts.',
	},
	lineSocialLogin: {
		content: '<p>Allow users to log in using their Line account for quick and simple authentication.</p>',
		helpingText: 'Enable one-click login via Line accounts.',
	},
	githubSocialLogin: {
		content: '<p>Allow users to log in using their GitHub account for developer-friendly authentication.</p>',
		helpingText: 'Enable one-click login via GitHub.',
	},
	discordSocialLogin: {
		content: '<p>Allow users to log in using their Discord account for quick authentication.</p>',
		helpingText: 'Enable one-click login via Discord.',
	},

	// Recent Activity (dashboard section)
	recentActivity: {
		content: '<p>View lockouts and failed login attempts in one place. Switch between Login Attempt Logs and Failed Login Attempts to monitor security.</p>',
	},

	// Logging Settings (enumeration logs page)
	loggingSettings: {
		content: '<p>Enable to monitor username discovery attempts securely.</p>',
	},

	// User Enumeration - Enable Protection label
	userEnumerationProtectionEnable: {
		content: '<p>Enable to hide usernames to strengthen site security.</p>',
	},
};
