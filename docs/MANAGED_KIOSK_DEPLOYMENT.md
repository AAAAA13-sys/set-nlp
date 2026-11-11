# School AAAAA13 — EvaluaTech
# Managed Kiosk Deployment Plan for Computer Laboratories

**Document Version:** 1.0 (AY 2026–2027)  
**Classification:** Institutional IT Infrastructure & Laboratory Administration  
**Target Environments:** University Computer Laboratories, Proctored Testing Centers, and Registrar Terminals  

---

## 1. Executive Summary: Fullscreen Web Mode vs. Managed Kiosk Mode

A fundamental architectural distinction exists between **Web Application Fullscreen Mode** and **Hardware/OS Managed Kiosk Mode**:

```
+-----------------------------------+------------------------------------------+
| Standard Web Fullscreen Mode      | Managed Kiosk Mode                       |
| (Student Personal Devices)        | (School AAAAA13 University Computer Labs)           |
+-----------------------------------+------------------------------------------+
| - Browser JavaScript API          | - Operating System level policy          |
| - Focus assistance (F11 / API)    | - Complete hardware shortcut blocking    |
| - User can exit anytime (Escape)  | - Admin password required to exit        |
| - Zero software installation      | - Dedicated institutional device setup   |
| - Runs on mobile, laptop, tablet  | - Fixed Windows/Linux lab workstations   |
| - Voluntary concentration aid     | - Institutional examination environment  |
+-----------------------------------+------------------------------------------+
```

### Critical Policy Guardrails
1. **Never Enforce Kiosk Lockdown on Personal Devices:** Students using personal smartphones, laptops, or home computers must never have intrusive device-level lockdown scripts or unclosable window loops imposed upon them. The web application provides voluntary, focused fullscreen mode (`document.documentElement.requestFullscreen()`) with graceful degradation and visible exit controls.
2. **Never Treat Ordinary Browser Mode as Kiosk-Secured:** Web browser tabs cannot physically block `Alt+Tab`, `Windows Key`, `Ctrl+Alt+Delete`, or OS notifications. System documentation and administrative reports must never falsely claim that browser-based SET evaluation constitutes an uncircumventable device lockdown.
3. **No Automatic Penalization on Loss of Focus:** In accordance with School AAAAA13 student privacy standards, transient focus changes (switching tabs, notifications, or exiting fullscreen) must **never** be used to invalidate evaluations, auto-submit unfinished drafts, or trigger academic misconduct reports.

---

## 2. Institutional Laboratory Kiosk Configuration

For high-stakes evaluation periods conducted in supervised university computer laboratories (e.g., CCS Computer Laboratories 1–4, Engineering CAD Labs), laboratory network administrators can configure workstations into dedicated kiosk mode using standard enterprise tools.

### A. Windows 11 / Windows 10 Enterprise (Assigned Access Kiosk)

Windows Assigned Access allows a single Universal Windows Platform (UWP) app or Microsoft Edge to run in a locked-down, full-screen interactive display.

#### Step 1: Dedicated Local Kiosk User Account
Create a non-privileged local account on the lab master image:
```powershell
# Run in Administrator PowerShell
net user School AAAAA13StudentKiosk EvaluatechLab2026! /add /passwordchg:no
wmic useraccount where name='School AAAAA13StudentKiosk' set PasswordExpires=FALSE
```

#### Step 2: Configure Microsoft Edge Kiosk Mode
In Windows Settings (`Accounts > Other users > Set up a kiosk`), select `School AAAAA13StudentKiosk` and specify **Microsoft Edge**:
- **Kiosk Mode Type:** Digital Signage / Interactive Kiosk (Full-Screen).
- **Target URL:** `http://127.0.0.1:8000/queue` (or institutional domain `https://evaluatech.aaaaa13.edu/queue`).
- **Idle Timeout:** 15 minutes (prompts user before session reset).

#### Step 3: Enforce Keyboard Filtering via Group Policy
Enable the Windows Keyboard Filter (`Turn on Keyboard Filter`) under `Computer Configuration > Administrative Templates > System > Keyboard Filter`:
- **Blocked Combinations:**
  - `Windows Key`
  - `Alt + Tab`
  - `Alt + F4`
  - `Ctrl + Shift + Esc`
  - `Ctrl + Esc`
- **Permitted Combinations (Proctor Access Only):**
  - `Ctrl + Alt + Delete` (bound to administrator password screen for lab proctors).

---

### B. Linux / Ubuntu Desktop Laboratories (LightDM Kiosk)

For open-source or dual-boot Linux laboratories:

#### Step 1: Create Dedicated Session Script
Create `/usr/local/bin/evaluatech-kiosk.sh`:
```bash
#!/usr/bin/env bash
# Prevent screen blanking and power management
xset s off
xset -dpms
xset s noblank

# Start Chromium or Google Chrome in kiosk mode
chromium-browser \
  --kiosk \
  --noerrdialogs \
  --disable-infobars \
  --no-first-run \
  --fast \
  --fast-start \
  --disable-suggestions-ui \
  --disable-translate \
  --disable-save-password-bubble \
  --disable-session-crashed-bubble \
  --incognito \
  --check-for-update-interval=31536000 \
  "http://127.0.0.1:8000/queue"
```

Make executable:
```bash
chmod +x /usr/local/bin/evaluatech-kiosk.sh
```

#### Step 2: Auto-login Kiosk Configuration
In `/etc/lightdm/lightdm.conf.d/99-evaluatech-kiosk.conf`:
```ini
[Seat:*]
autologin-user=plpstudent
autologin-session=evaluatech-kiosk
```

---

## 3. Administrator & Proctor Recovery Procedures

In the event of a student station malfunction, network disconnection, or physical lab evacuation:

### Procedure 1: Emergency Proctor Exit (Windows Kiosk)
1. Press `Ctrl + Alt + Delete`.
2. Select **Sign Out** or **Switch User**.
3. Log in with the Laboratory Administrator credential (`School AAAAA13-LabAdmin`).
4. Launch Task Manager or command prompt to inspect network logs.

### Procedure 2: Interrupted Evaluation Recovery
1. EvaluaTech continuously autosaves student rating and qualitative drafts in local session storage (`sessionStorage`) without transmitting unsubmitted drafts over the wire.
2. If a workstation is accidentally rebooted:
   - The student logs back in with their student institutional credentials (`2024-XXXXX`).
   - The application checks the evaluation queue.
   - The student clicks `Start Evaluation` on their in-progress subject; their saved draft ratings and written feedback are automatically restored.
3. No clearance is issued and no vault submission occurs until the student clicks `Submit Evaluation` on Step 2.

### Procedure 3: Institutional Evaluation Window Closure
- If the university evaluation window reaches `ends_at` (or an administrator triggers `SUSPEND` or `CLOSE` from the Registrar console):
  - In-flight requests are evaluated by the backend server clock (`DecoupledEvaluationService`).
  - Workstations receive an institutional notification banner: `"Evaluations are currently closed for this semester."`
  - Unsubmitted drafts remain preserved on client storage but cannot be posted into the vault after the deadline has expired.

---

## 4. Hardware and Network Architecture

```
+---------------------------------------------------------------------------------+
|                       School AAAAA13 Campus Core Network (VLAN 40)                         |
+---------------------------------------------------------------------------------+
                                      |
         +----------------------------+----------------------------+
         |                                                         |
+----------------------------------+             +----------------------------------+
| CCS Laboratory 1 (45 Terminals)  |             | CCS Laboratory 2 (45 Terminals)  |
| Windows 11 Enterprise Kiosk      |             | Ubuntu 24.04 LTS Kiosk           |
| Assigned Access (Edge Fullscreen)|             | LightDM Dedicated Chromium Session|
+----------------------------------+             +----------------------------------+
         |                                                         |
         +----------------------------+----------------------------+
                                      |
                     +----------------------------------+
                     | Institutional Gateway / Firewall |
                     | Port 8000 (HTTP) / 443 (HTTPS)   |
                     | Loopback 127.0.0.1:5001 (NLP)    |
                     +----------------------------------+
                                      |
                     +----------------------------------+
                     | EvaluaTech Application Cluster   |
                     | - Laravel 12 HTTP API            |
                     | - Decoupled Cryptographic Vault  |
                     | - calamanCy + VADER NLP Engine   |
                     +----------------------------------+
```

---

## 5. Summary of Compliance

* **Data Privacy Act (RA 10173):** Physical laboratory workstations clear session storage, cookies, and cache upon user sign-out or session reset.
* **Accessibility (WCAG 2.2 AA):** High-contrast mode, standard font sizing (`16px` body), and keyboard navigation (`Tab`, `Space`, `Enter`, Arrow keys) function identically in kiosk mode as in desktop browsers.
* **Academic Integrity:** Prevents casual desktop multitasking in proctored laboratories while maintaining respectful student ergonomics on personal mobile and home devices.

