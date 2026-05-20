{{-- @formatter:off --}}
@php
    $buttonColor     = config('finisterre.mail.theme.button_color', '#18181b');
    $buttonTextColor = config('finisterre.mail.theme.button_text_color', '#ffffff');
    $linkColor       = config('finisterre.mail.theme.link_color', '#18181b');
    $headingColor    = config('finisterre.mail.theme.heading_color', '#18181b');
    $bodyBackground  = config('finisterre.mail.theme.body_background', '#fafafa');
    $cardBackground  = config('finisterre.mail.theme.card_background', '#ffffff');
@endphp
/* Base */

body,
body *:not(html):not(style):not(br):not(tr):not(code) {
    box-sizing: border-box;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif,
        'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
    position: relative;
}

body {
    -webkit-text-size-adjust: none;
    background-color: #ffffff;
    color: #52525b;
    height: 100%;
    line-height: 1.4;
    margin: 0;
    padding: 0;
    width: 100% !important;
}

p,
ul,
ol,
blockquote {
    line-height: 1.4;
    text-align: start;
}

a {
    color: {{ $linkColor }};
}

a img {
    border: none;
}

/* Typography */

h1 {
    color: {{ $headingColor }};
    font-size: 18px;
    font-weight: bold;
    margin-top: 0;
    text-align: start;
}

h2 {
    font-size: 16px;
    font-weight: bold;
    margin-top: 0;
    text-align: start;
}

h3 {
    font-size: 14px;
    font-weight: bold;
    margin-top: 0;
    text-align: left;
}

p {
    font-size: 16px;
    line-height: 1.5em;
    margin-top: 0;
    text-align: left;
}

p.sub {
    font-size: 12px;
}

img {
    max-width: 100%;
}

/* Layout */

.wrapper {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: {{ $bodyBackground }};
    margin: 0;
    padding: 0;
    width: 100%;
}

.content {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 0;
    padding: 0;
    width: 100%;
}

/* Header */

.header {
    padding: 25px 0;
    text-align: center;
}

.header a {
    color: {{ $headingColor }};
    font-size: 19px;
    font-weight: bold;
    text-decoration: none;
}

/* Logo */

.logo {
    height: 75px;
    margin-top: 15px;
    margin-bottom: 10px;
    max-height: 75px;
    width: 75px;
}

/* Body */

.body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: {{ $bodyBackground }};
    border-bottom: 1px solid {{ $bodyBackground }};
    border-top: 1px solid {{ $bodyBackground }};
    margin: 0;
    padding: 0;
    width: 100%;
}

.inner-body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    background-color: {{ $cardBackground }};
    border-color: #e4e4e7;
    border-radius: 8px;
    border-width: 1px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
    margin: 0 auto;
    padding: 0;
    width: 570px;
}

.inner-body a {
    word-break: break-all;
}

/* Subcopy */

.subcopy {
    border-top: 1px solid #e4e4e7;
    margin-top: 25px;
    padding-top: 25px;
}

.subcopy p {
    font-size: 14px;
}

/* Footer */

.footer {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    margin: 0 auto;
    padding: 0;
    text-align: center;
    width: 570px;
}

.footer p {
    color: #a1a1aa;
    font-size: 12px;
    text-align: center;
}

.footer a {
    color: #a1a1aa;
    text-decoration: underline;
}

/* Tables */

.table table {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 30px auto;
    width: 100%;
}

.table th {
    border-bottom: 1px solid #e4e4e7;
    margin: 0;
    padding-bottom: 8px;
}

.table td {
    color: #52525b;
    font-size: 15px;
    line-height: 18px;
    margin: 0;
    padding: 10px 0;
}

.content-cell {
    max-width: 100vw;
    padding: 32px;
}

/* Buttons */

.action {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 30px auto;
    padding: 0;
    text-align: center;
    width: 100%;
    float: unset;
}

.button {
    -webkit-text-size-adjust: none;
    border-radius: 6px;
    color: {{ $buttonTextColor }};
    display: inline-block;
    overflow: hidden;
    text-decoration: none;
}

.button-blue,
.button-primary {
    background-color: {{ $buttonColor }};
    border-bottom: 8px solid {{ $buttonColor }};
    border-left: 18px solid {{ $buttonColor }};
    border-right: 18px solid {{ $buttonColor }};
    border-top: 8px solid {{ $buttonColor }};
}

.button-green,
.button-success {
    background-color: #16a34a;
    border-bottom: 8px solid #16a34a;
    border-left: 18px solid #16a34a;
    border-right: 18px solid #16a34a;
    border-top: 8px solid #16a34a;
}

.button-red,
.button-error {
    background-color: #dc2626;
    border-bottom: 8px solid #dc2626;
    border-left: 18px solid #dc2626;
    border-right: 18px solid #dc2626;
    border-top: 8px solid #dc2626;
}

/* Panels */

.panel {
    border-left: {{ $buttonColor }} solid 4px;
    margin: 21px 0;
}

.panel-content {
    background-color: #fafafa;
    color: #52525b;
    padding: 16px;
}

.panel-content p {
    color: #52525b;
}

.panel-item {
    padding: 0;
}

.panel-item p:last-of-type {
    margin-bottom: 0;
    padding-bottom: 0;
}

/* Utilities */

.break-all {
    word-break: break-all;
}
{{-- @formatter:on --}}
