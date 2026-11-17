@extends('hukum.tata', [
  'lang'    => 'en',
  'judul'   => 'Delete Account',
  'tanggal' => 'Effective 27 September 2026',
  'ringkas' => 'How to delete your EQOHSEE account and the personal data attached to it.',
  'alih'    => 'Versi Bahasa Indonesia: <a href="/hapus-akun">Hapus Akun</a>',
  'kaki'    => 'Mining HSE and occupational safety management',
])

@section('isi')

<p>This page explains how to delete an <strong>EQOHSEE</strong> account — the web
application at <code>eqohsee.id</code> and the Android application
<code>id.eqohsee.eqohsee</code> — and which data is deleted with it.</p>

<h2>1. Delete it yourself in the app</h2>

<ol>
  <li>Sign in to EQOHSEE with the account you want to delete.</li>
  <li>Open <strong>Profile → Account &amp; password</strong>
      (on the web: account menu → <strong>Profile</strong>).</li>
  <li>In the <strong>Delete Account</strong> section, tap <strong>Delete
      Account</strong>, type your password and confirm.</li>
</ol>

<p>The account is deleted <strong>immediately</strong>, sessions on every device
end, and you can no longer sign in with it.</p>

<h2>2. Ask us to delete it</h2>

<p>If you can no longer sign in — forgotten password, lost device, or you no
longer work for a company that uses EQOHSEE — send a request to:</p>

<div class="kotak">
  <p><strong>Account deletion request</strong></p>
  <p>Email: <a href="mailto:{{ $surel }}?subject=EQOHSEE%20account%20deletion%20request&amp;body=Account%20email%3A%20%0ACompany%3A%20%0AName%3A%20">{{ $surel }}</a><br>
  Include the account email address, your name, and your company.</p>
</div>

<p>We verify the requester's identity first, then delete the account
<strong>within 30 days</strong> of receiving the request. You will receive a
reply when the deletion is complete.</p>

<p>If your account was created by your company (HR or HSE department), you may
also ask your company administrator.</p>

<h2>3. Data that is deleted</h2>

<ul>
  <li>Account: name, email address, encrypted password, position, profile photo,
      settings and two-step verification</li>
  <li>Sign-in sessions and device tokens</li>
  <li>The link between your account and the data you entered — those records no
      longer point to your account</li>
  <li>Data on your device: unsent reports, drafts and field-mode screen copies
      are removed when you sign out or uninstall the app</li>
</ul>

<h2>4. Data that is retained</h2>

<p>Some safety and employment records <strong>must be kept by the
company</strong> under applicable regulations and are not deleted with the
account:</p>

<ul>
  <li>Hazard reports, inspections, work permits and pre-start checks already
      submitted — they remain company safety records, with the reporter's name
      as written on the report</li>
  <li>Workplace accident records and their investigations</li>
  <li>Medical check-up (MCU) results and employment documents</li>
</ul>

<p>Those records are kept for their statutory retention period applicable to your
company and then deleted by the company. The audit trail of changes to important
records (who changed what, and when) is kept together with the records it
describes.</p>

<p>Full details of the data we collect are in the
<a href="/privacy-policy">Privacy Policy</a>.</p>

@endsection
