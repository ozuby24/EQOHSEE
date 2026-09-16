@extends('hukum.tata', [
  'lang'    => 'en',
  'judul'   => 'Privacy Policy',
  'tanggal' => 'Effective 16 September 2026',
  'ringkas' => 'How EQOHSEE collects, uses and protects data in its mining HSE and occupational safety application.',
  'alih'    => 'Versi Bahasa Indonesia: <a href="/kebijakan-privasi">Kebijakan Privasi</a>',
  'kaki'    => 'Mining HSE and occupational safety management',
])

@section('isi')

<p>This policy explains how <strong>EQOHSEE</strong> — the web application at
<code>eqohsee.id</code> and the Android application
<code>id.eqohsee.eqohsee</code> — collects, uses, stores and protects personal
data.</p>

<div class="kotak">
  <p><strong>Who is responsible for your data</strong></p>
  <p>EQOHSEE is used by mining companies to manage their own workforce data. In
  almost every case, <strong>the company you work for is the Data
  Controller</strong> — it decides what is collected and how long it is kept.
  EQOHSEE acts as a <strong>Data Processor</strong>: we process that data on
  their instructions, not for our own purposes.</p>
  <p>So if you are a worker and want to see, correct or delete your data, that
  request goes <strong>to your company</strong> (usually HR or the HSE
  department), not to us. We are not authorised to delete a company's records
  at an individual's request.</p>
</div>

<h2>1. Data we collect</h2>

<p>The list below is the actual one, compiled from what the application really
stores — not a generic list.</p>

<table>
  <tr><th>Category</th><th>Contents</th><th>Source</th></tr>
  <tr>
    <td>Account</td>
    <td>Name, email address, hashed password, job title, company, access role</td>
    <td>Entered at account creation</td>
  </tr>
  <tr>
    <td>Worker records</td>
    <td>Name, national ID number, employee number, position, competencies,
        certificate and permit expiry dates</td>
    <td>Entered by company HR/HSE</td>
  </tr>
  <tr>
    <td><strong>Precise location</strong></td>
    <td>Latitude and longitude of each attendance scan, distance to the work
        area boundary, and whether the scan happened inside it</td>
    <td>Field attendance terminals</td>
  </tr>
  <tr>
    <td><strong>Photographs of people</strong></td>
    <td>Selfie captured at clock-in or clock-out</td>
    <td>Attendance terminal or app</td>
  </tr>
  <tr>
    <td><strong>Biometric reference</strong></td>
    <td>An enrolment reference number for the fingerprint registered on the
        attendance terminal. <em>The fingerprint template itself is not stored
        in EQOHSEE</em> — it stays inside the terminal.</td>
    <td>Attendance terminal</td>
  </tr>
  <tr>
    <td><strong>Health data</strong></td>
    <td>Medical check-up (MCU) submissions and their status, together with any
        attached documents</td>
    <td>Uploaded by company or clinic</td>
  </tr>
  <tr>
    <td>Attendance and working time</td>
    <td>Roster schedules, clock-in and clock-out times, lateness, leave,
        overtime, and the payroll calculations derived from them</td>
    <td>Attendance terminals and HR entry</td>
  </tr>
  <tr>
    <td>Uploaded documents</td>
    <td>Hazard reports, inspection results, audit evidence, work permits,
        procedures, and attached photographs</td>
    <td>Uploaded by users</td>
  </tr>
  <tr>
    <td>Technical records</td>
    <td>IP address, browser or device type, sign-in times, and an audit trail
        of actions on sensitive records</td>
    <td>Recorded automatically</td>
  </tr>
</table>

<div class="kotak">
  <p><strong>Specific personal data</strong></p>
  <p>Under Indonesian Law No. 27 of 2022 on Personal Data Protection,
  <strong>health data and biometric data are classified as specific personal
  data</strong> and require stricter protection than ordinary data. In EQOHSEE
  both are visible only to roles that genuinely need them, and each access is
  logged.</p>
</div>

<h2>2. How data is used</h2>

<ul>
  <li>Creating and managing accounts and their access rights</li>
  <li>Running the modules a company uses: hazard reporting, inspections, SMKP
      audits, work permits, rosters, attendance, leave, overtime and payroll</li>
  <li>Confirming a worker is fit and eligible before entering a work area —
      valid competencies, valid medical check-up, complete permits</li>
  <li>Verifying that attendance happened at the right place at the right time</li>
  <li>Meeting statutory reporting obligations to the relevant authorities</li>
  <li>Keeping the system secure and investigating misuse</li>
  <li>Diagnosing technical faults</li>
</ul>

<p><strong>We do not sell personal data, and we do not use it for advertising or
marketing of any kind.</strong></p>

<h2>3. Legal basis</h2>

<p>Processing rests on compliance with mining-safety legal obligations, on
performance of the employment relationship between a worker and their company,
and — for specific personal data such as health and biometric data — on
<strong>consent that the company must obtain from the worker concerned</strong>.</p>

<h2>4. Location tracking and attendance selfies</h2>

<p>We set this out separately because it is the part people whose data is
recorded most often do not realise.</p>

<p>Each time a worker clocks in or out at a field terminal, EQOHSEE stores the
coordinates of that event, the distance to the work-area boundary and — where
the terminal captures one — a selfie. There is one purpose: confirming that
attendance was performed by that person at their work location, rather than
delegated to someone else.</p>

<p>This recording happens <strong>only at the moment of the attendance
scan</strong>. EQOHSEE does not track anyone's position continuously, and the
Android application does not request location permission at all.</p>

<h2>5. Storage and security</h2>

<ul>
  <li>All traffic runs over HTTPS</li>
  <li>Passwords are stored hashed, never as plain text</li>
  <li>Each company's data is isolated at the database level; one company cannot
      read another's data</li>
  <li>Access is restricted by role, and actions on sensitive records are logged</li>
  <li>Two-factor authentication is available for accounts</li>
  <li>Data is stored on servers located in Indonesia</li>
</ul>

<p>Even so, no electronic system can be guaranteed completely secure.</p>

<h2>6. Data sharing</h2>

<p>Data is shared only with:</p>

<ul>
  <li><strong>The company that owns the data</strong> and the users it grants access to</li>
  <li><strong>Infrastructure providers</strong> running servers, storage and email
      delivery — limited to what is needed to operate the service</li>
  <li><strong>Authorities</strong>, where required by law or a valid official request</li>
</ul>

<p>We do not share data with third parties for commercial purposes.</p>

<h2>7. Retention</h2>

<p>Retention periods are set by the company that owns the data and by applicable
regulation. Some safety and employment records must be kept for years after
employment ends, and therefore cannot be deleted on an individual request.</p>

<h2>8. Your rights</h2>

<p>Under Law No. 27 of 2022 you may request an explanation of the processing,
obtain a copy of your data, correct inaccuracies, withdraw consent, object to
processing, and request erasure so far as this does not conflict with retention
obligations.</p>

<p><strong>How to exercise them:</strong></p>

<ul>
  <li><strong>If you are a worker</strong> — contact your company's HR or HSE
      department. They are the Data Controller and the decision is theirs.</li>
  <li><strong>If you registered your own account</strong> — contact us at the
      address in section 11.</li>
</ul>

<h2>9. Account deletion</h2>

<p>You may request deletion of your account and the personal data attached to it
via the address in section 11, or through your company administrator. Requests
are processed within a reasonable period once the requester's identity is
confirmed.</p>

<p>Records that must be retained by law — such as workplace accident records,
medical check-ups and employment documents — are kept until their statutory
retention period ends, even after the account is deleted.</p>

<h2>10. Children</h2>

<p>EQOHSEE is a workplace application for the mining industry and is not directed
at children. We do not knowingly collect children's data. Minimum working age in
this sector is governed by applicable labour regulation.</p>

<h2>11. Contact us</h2>

<p>For questions about this policy, data deletion requests, or to report a
suspected data breach:</p>

<div class="kotak">
  <p><strong>EQOHSEE</strong></p>
  <p>Email: <a href="mailto:{{ $surel }}">{{ $surel }}</a><br>
  Website: <a href="https://eqohsee.id">eqohsee.id</a></p>
</div>

<h2>12. Changes to this policy</h2>

<p>This policy may be updated. Changes are published on this page with a new
effective date. Significant changes are notified to client companies before they
take effect.</p>

@endsection
