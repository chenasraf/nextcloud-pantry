---
title: Pantry Privacy Policy
description: How the Pantry server app and the Pantry companion apps handle your data.
---

_Effective Date: October 30, 2026_

Chen Asraf ("we", "our", or "us") built Pantry as free, open-source software, provided at no cost
and intended for use as is. This policy covers both parts of Pantry:

- **The server app** — the Pantry app installed on a Nextcloud server, including its web interface.
- **The companion apps** — Pantry for Android, iOS, Wear OS, macOS, Windows and Linux.

---

### 1. Summary

- We do **not** collect, receive, or store any of your data.
- We do **not** operate any servers, databases, or cloud services for Pantry.
- Your data lives on **your own Nextcloud server**, and on your devices as a local cache.
- There are **no analytics, ads, crash reporting, tracking, or social media plugins** in any part of
  Pantry.
- Some features use **third-party services** — they are listed in section 5.

---

### 2. Who Is Responsible

- **Chen Asraf** is the author of Pantry and of this policy. We do not act as data controller or
  processor for your data, because none of it reaches us.
- **The operator of your Nextcloud server** — you, or your server's administrator — is the data
  controller for everything stored on that server.
- **Third-party services** (section 5) are controllers for the data they receive, under their own
  policies.

---

### 3. Server App

- Everything you add — houses, lists, items, photos, notes and shopping trips — is stored in your
  Nextcloud server's database and files.
- Looked-up barcodes are kept in a cache on your server, shared by everyone on that server. It holds
  product details only, not who scanned them.
- The server app itself makes no connections to outside services.

---

### 4. Companion Apps

#### Sign-in

- The apps sign in with the Nextcloud Login Flow v2.
- Your server address and an app-specific password are kept on your device in encrypted storage.
- Your credentials are never sent to us.

#### Data on your device

- The apps cache lists, items, photos, notes and preferences on your device for offline use and
  faster loading.
- Photos you upload are sent directly to your Nextcloud server.

#### Wear OS watch

A watch can be signed in in two ways:

- **From the phone app** — uses the Wear OS Data Layer, provided by Google Play services:
  - After you confirm on the phone, it sends the watch your server address, app password and any
    server certificates you accepted, in a one-time message that is not stored.
  - While paired, the phone sends the watch the lists it is showing, and passes the watch's requests
    to your server when the watch cannot reach it directly.
  - A few settings — the paired watch, accepted certificates, language and appearance — are stored
    in the Data Layer and may be included in your Android device's Google backup.
  - This traffic normally travels over Bluetooth or Wi-Fi, but Google may relay it through its
    servers when the phone and watch are not directly connected.
- **With a QR code** — the code opens your Nextcloud's own login page on any phone, and the watch
  gets its password from your server directly. This does not use Google Play services, and is the
  only option in the F-Droid build.

---

### 5. Third-Party Services

- **Nextcloud** _(both)_ — stores your data and handles sign-in. Your server's administrator is
  responsible for it.
- **Open Food Facts** _(both)_ — looks up product details when you scan or enter a barcode that is
  not already in your server's cache:
  - The lookup is made directly from your device, and only the barcode number is sent.
  - If you fill in the product photo, it is downloaded from Open Food Facts and saved on your
    Nextcloud server.
  - See the [Open Food Facts privacy policy](https://world.openfoodfacts.org/privacy).
- **Google Play services** _(Wear OS)_ — connects the phone app and a watch signed in from it, as
  described in section 4. See the [Google privacy policy](https://policies.google.com/privacy).
- **App stores** _(companion apps)_ — Google Play, the App Store and F-Droid may collect data under
  their own policies when you download the apps.

We do **not** sell, rent, or share your data with anyone.

---

### 6. Legal Basis

- We process no personal data, so we rely on no legal basis for processing.
- Pantry contacts a third party only when you use the feature that needs it — scanning a barcode, or
  signing a watch in from the phone. This is necessary to provide the feature you asked for.
- Processing on your Nextcloud server happens under the legal basis of its operator.

---

### 7. Permissions

The companion apps may ask for:

- **Internet** — to connect to your Nextcloud server.
- **Camera / Photo Library** — to add photos and scan barcodes.

The web interface asks your browser for the camera only when you scan a barcode. You can revoke any
permission in your device or browser settings.

---

### 8. Data Retention

- **We** keep no data, so there is nothing for us to retain.
- **On your server** — your data is kept until you or your administrator delete it. The barcode
  cache is kept until your administrator clears it.
- **On your devices** — cached data is kept until you log out or clear the app's storage.

---

### 9. Your Rights

Under the GDPR and similar laws, you have the right to access, correct, delete, restrict, object to
the processing of, and receive a copy of your personal data.

- **Data on your server** — exercise these rights in Pantry itself, or with your server's
  administrator.
- **Data on your devices** — log out of the app, or clear its storage in your device settings.
- **App access** — revoke the app password in your Nextcloud security settings at any time.
- **Third-party services** — contact the service, using the links in section 5.

You also have the right to lodge a complaint with the data protection supervisory authority in the
country where you live or work. In the EU, the
[European Data Protection Board](https://www.edpb.europa.eu/about-edpb/about-edpb/members_en) lists
every national authority.

---

### 10. Security

- All connections to your Nextcloud server use the security your server provides, such as HTTPS.
- Credentials are kept in your device's encrypted storage.
- No method of transmission or storage is completely secure, and we cannot guarantee absolute
  security.

---

### 11. Children's Privacy

Pantry is not intended for anyone under the age of 13. We do not knowingly collect personal
information from children under 13.

---

### 12. Changes to This Policy

We may update this policy from time to time. Changes are posted on this page with a new effective
date. Please review this page periodically.

---

### 13. Contact Us

For privacy questions or requests about this policy:

**Chen Asraf** Email: contact [at] casraf [dot] dev

---

**&copy; 2026 Chen Asraf. All rights reserved.**
