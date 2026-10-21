# Laravel + Filament Admin Panel — Implementation Requirements

You are implementing the **Admin Panel and Sub-Admin Panel** for a car auction management platform.

## Existing Setup

* Laravel project is already created.
* Filament Admin Panel is already installed and configured.
* Do NOT recreate the Laravel project.
* Do NOT recreate the Filament installation.
* Work inside the existing Laravel application.
* Follow Laravel and Filament best practices.
* Build the system in a modular, maintainable way.
* Use migrations, models, relationships, policies, services/actions, Filament Resources, Pages, Widgets, Notifications, and Jobs where appropriate.
* Do not put complex business logic directly inside Filament Resource classes.
* Keep business logic in dedicated Services/Actions.
* Use database transactions for critical operations such as auction publishing, bid/result processing, and payment-related status changes.

---

# 1. ADMIN PANEL OBJECTIVE

Build a complete web-based administration system for managing:

* Customers
* KYC information
* Cars
* Car images and documents
* Car locations
* Inspections
* Auctions
* Auction participants
* Auction payments/entry fees
* Live auction monitoring
* Auction results
* Winners
* Financial records
* Notifications
* Staff/sub-admin users
* Roles and permissions
* Audit logs
* Reports
* Dashboard statistics

The admin panel should provide a clean, professional Filament interface suitable for daily operational use.

---

# 2. USER ROLES & ACCESS CONTROL

Implement role-based access control.

Use a proper permission system, preferably compatible with Filament.

Required roles:

### Super Admin

Full access to everything.

### Auction Manager

Can:

* Create auctions
* Edit auctions
* Publish/unpublish auctions
* Configure auction dates/times
* Configure starting price
* Configure minimum bid increment
* View participants
* Monitor live auctions
* View auction results

Cannot manage system administrators or financial configuration.

### Car / Inventory Manager

Can:

* Create cars
* Edit cars
* Upload car images
* Upload car documents
* Manage car specifications
* Manage car location
* Manage car status
* Schedule/manage inspection information

### Inspection Staff

Can:

* View assigned inspections
* Update inspection status
* Add inspection notes
* Upload inspection photographs/documents
* Mark inspection as completed

### Finance Manager

Can:

* View entry-fee/payment records
* View payment status
* View successful payments
* View refunded/unblocked payments
* View captured winner fees
* View financial reports

Should NOT be able to modify auction configuration.

### Customer Support

Can:

* View customers
* View customer KYC information according to permission
* View auction participation
* View payment status
* View inspection bookings
* View auction results
* Add internal support notes

Should have read-focused access wherever possible.

---

# 3. CUSTOMER MANAGEMENT

Create a Customer management module.

Customer fields should include:

* ID
* Full name
* Email
* Mobile number
* Password/authentication-related status
* Account status
* KYC status
* KYC submitted date
* KYC approved date
* KYC rejection reason
* Profile information
* Address
* City
* State
* Pincode
* Country
* Created date
* Last login
* Account blocked/unblocked status

Customer statuses:

* Active
* Inactive
* Blocked
* Suspended

KYC statuses:

* Pending
* Under Review
* Approved
* Rejected

Admin should be able to:

* View customer
* Search customer
* Filter customer
* View customer profile
* Review KYC
* Approve KYC
* Reject KYC with reason
* Block/unblock customer
* View customer's auction participation
* View customer's payments
* View inspection bookings
* View auction history

Do not expose sensitive information unnecessarily to users without permission.

---

# 4. KYC MANAGEMENT

Create a dedicated KYC management interface.

KYC information may include:

* PAN
* Aadhaar or other approved identity document
* Identity document number/reference
* Address proof
* Identity document uploads
* Selfie/profile verification if applicable
* Verification status
* Rejection reason
* Reviewer
* Reviewed timestamp

Admin should be able to:

* Review documents
* Approve KYC
* Reject KYC
* Add rejection reason
* See verification history

All KYC status changes must be logged.

---

# 5. CAR / INVENTORY MANAGEMENT

Create a Filament Car Resource.

Car fields:

### Basic Information

* Stock/Inventory ID
* Make
* Model
* Variant
* Manufacturing year
* Registration year
* Fuel type
* Transmission
* Body type
* Color
* Kilometer
* Number of owners
* Registration number
* VIN/chassis number where applicable
* Engine number where applicable

### Pricing

* Expected price
* Reserve price if applicable
* Starting auction price
* Current status

### Location

* Location name
* Address
* City
* State
* Pincode
* Latitude
* Longitude

### Description

* Short description
* Detailed description
* Features/specifications

### Media

* Multiple car images
* Primary image
* Inspection images
* Videos if supported

### Documents

Allow uploading relevant documents such as:

* RC
* Insurance
* Inspection report
* Service records
* Other supporting documents

Use proper storage configuration and authorization.

Car statuses:

* Draft
* Available
* Inspection Scheduled
* Auction Scheduled
* Live Auction
* Sold
* Unsold
* Removed
* Archived

Admin should be able to:

* Create car
* Edit car
* Delete/archive car
* Upload multiple images
* Reorder images
* Set primary image
* Upload documents
* Manage location
* View associated auctions
* View inspection history

---

# 6. INSPECTION MANAGEMENT

Create an Inspection module.

Inspection fields:

* Car
* Customer
* Assigned staff
* Inspection date
* Start time
* End time
* Location
* Status
* Notes
* Inspection report
* Photos/documents

Inspection statuses:

* Requested
* Scheduled
* Assigned
* Confirmed
* In Progress
* Completed
* Cancelled
* No Show

Admin functionality:

* Schedule inspection
* Assign inspection staff
* Reschedule
* Cancel
* View calendar/list
* Update inspection status
* Add notes
* Upload inspection documents
* View customer inspection history

Prevent invalid scheduling conflicts where possible.

---

# 7. AUCTION MANAGEMENT

Create an Auction Resource.

Auction fields:

* Auction ID
* Car
* Auction title
* Description
* Start date
* Start time
* End date
* End time
* Starting price
* Minimum bid increment
* Reserve price if applicable
* Participation fee
* Auction status
* Published status
* Created by
* Published by
* Published timestamp

Auction statuses:

* Draft
* Scheduled
* Registration Open
* Live
* Completed
* Cancelled
* Suspended

Admin should be able to:

* Create auction
* Edit auction
* Schedule auction
* Publish auction
* Unpublish auction
* Cancel auction
* Configure pricing
* Configure timing
* View participants
* View bids
* View auction activity
* Close auction
* Generate result

Validation:

* Auction must have a valid car.
* Start date/time must be before end date/time.
* Starting price cannot be negative.
* Bid increment must be greater than zero.
* Prevent conflicting active auctions for the same car.
* Prevent publishing incomplete auctions.
* Prevent editing critical auction settings once the auction is live unless the user has explicit permission.

---

# 8. AUCTION PARTICIPANT MANAGEMENT

Create an Auction Participants interface.

Display:

* Customer
* Auction
* Registration status
* Entry fee amount
* Payment status
* KYC status
* Participation date
* Eligibility status

Participation statuses:

* Requested
* Payment Pending
* Payment Confirmed
* Eligible
* Rejected
* Cancelled

Admin should be able to:

* View participants
* Filter by auction
* Filter by payment status
* Filter by KYC status
* View customer details
* View payment details
* Approve/reject participation where applicable
* Check eligibility

A customer should not be considered eligible to participate unless all required conditions are satisfied.

---

# 9. PAYMENT / ENTRY FEE MANAGEMENT

Implement admin-side payment tracking.

The participation fee is currently **₹5,000**.

Do not hard-code this value throughout the application.

Create configurable auction/payment settings where appropriate.

Track:

* Payment ID
* Customer
* Auction
* Amount
* Currency
* Gateway
* Gateway transaction/reference ID
* Payment status
* Payment type
* Created date
* Successful date
* Captured date
* Released/refunded date
* Failure reason

Payment statuses:

* Pending
* Authorized/Held
* Captured
* Released
* Refunded
* Failed
* Cancelled

Admin should have a read-focused financial transaction interface.

Do not allow arbitrary manual manipulation of payment records.

Payment state changes must go through controlled service/action classes.

---

# 10. LIVE AUCTION MONITORING

Create a dedicated Filament Live Auction monitoring page.

The page should allow authorized admins to see:

* Auction
* Car
* Current auction status
* Remaining time
* Number of participants
* Number of bids
* Current highest bid
* Bid activity
* Latest bid timestamp
* Auction events

The admin should be able to:

* Monitor live activity
* Pause/suspend auction if business rules permit
* Resume auction if permitted
* End auction
* Handle operational issues
* View real-time bid activity

Use polling/realtime mechanisms appropriately.

Do not expose hidden customer bid prices to unauthorized users.

The customer-facing auction may use hidden individual bid prices until the auction ends, but the admin must have appropriate privileged visibility according to role.

---

# 11. BID MANAGEMENT

Create a Bid model/module.

Track:

* Auction
* Customer
* Bid amount
* Bid timestamp
* Bid sequence
* Bid status
* Source/device metadata where appropriate
* Created timestamp

Bid statuses may include:

* Valid
* Rejected
* Cancelled
* Winning
* Losing

Implement server-side validation for:

* Auction is live
* Customer is eligible
* Bid amount is valid
* Bid satisfies minimum increment
* Customer has valid participation/payment status

Do not rely on frontend validation.

For financial/auction integrity, bid creation must be handled atomically.

---

# 12. AUCTION RESULT MANAGEMENT

Create an Auction Result module.

After an auction ends:

* Determine winner according to approved auction rules.
* Store winner.
* Store winning bid.
* Store final auction state.
* Mark other participants as unsuccessful.
* Trigger the required payment settlement workflow.

Result fields:

* Auction
* Winner
* Winning bid
* Result status
* Finalized by
* Finalized timestamp
* Settlement status

Result statuses:

* Pending
* Finalized
* Settlement Pending
* Settled
* Cancelled

Admin should be able to:

* Review result
* Finalize result
* View winner
* View winning bid
* View participant results
* View settlement status

Result finalization must be protected from duplicate execution.

---

# 13. FINANCIAL DASHBOARD

Create a Finance dashboard/widget area.

Display:

* Total payments
* Pending payments
* Held entry fees
* Captured fees
* Released/refunded fees
* Failed payments
* Auction revenue
* Winning transaction value
* Revenue by date
* Revenue by auction

Provide filters:

* Date range
* Auction
* Payment status
* Customer

Use database aggregation rather than loading unnecessary records into PHP memory.

---

# 14. REPORTING

Create an admin Reports section.

Required reports:

### Auction Report

* Auction
* Car
* Start/end date
* Participants
* Number of bids
* Winning bid
* Winner
* Status

### Customer Report

* Customer registration
* KYC status
* Participation count
* Successful auctions
* Failed/unsuccessful auctions

### Payment Report

* Transaction
* Customer
* Auction
* Amount
* Status
* Gateway reference
* Date

### Inspection Report

* Car
* Customer
* Staff
* Date
* Status

### Bid Analytics

* Auction
* Total bids
* Unique bidders
* Highest bid
* Average bid
* Bid activity over time

Reports should support:

* Search
* Filtering
* Sorting
* Date ranges
* CSV/Excel export where appropriate

---

# 15. NOTIFICATION MANAGEMENT

Create an admin notification/log interface.

Track notifications sent through:

* Push
* SMS
* WhatsApp
* Email if applicable

Notification record:

* Customer
* Type
* Channel
* Event
* Message/template identifier
* Status
* Provider reference
* Sent timestamp
* Failure reason

Examples of events:

* Auction participation confirmed
* Inspection scheduled
* Auction reminder
* Auction starting
* Auction result
* Winner notification
* Payment status update

Actual external provider integration should be isolated behind service classes/interfaces.

---

# 16. DASHBOARD

Create a useful Filament admin dashboard.

Display:

### Overview

* Total customers
* Pending KYC
* Total cars
* Cars available
* Upcoming auctions
* Live auctions
* Completed auctions

### Financial

* Total entry fees
* Held amount
* Captured amount
* Released/refunded amount

### Auction

* Upcoming auctions
* Live auctions
* Recently completed auctions
* Recent winners
* Recent bidding activity

### Operations

* Pending inspections
* Today's inspections
* Unassigned inspections
* Failed payments
* Pending KYC

Widgets should respect the logged-in user's permissions.

---

# 17. SUB-ADMIN USER MANAGEMENT

Create a Staff/Admin Users module.

Fields:

* Name
* Email
* Mobile
* Password
* Role
* Status
* Last login
* Created date

Statuses:

* Active
* Inactive
* Suspended

Super Admin should be able to:

* Create staff user
* Assign role
* Modify permissions
* Activate/deactivate account
* Reset password
* View activity

A user must never gain permissions simply by manipulating frontend requests.

All authorization must be enforced server-side.

---

# 18. AUDIT LOGGING

Implement comprehensive audit logging.

Record important actions such as:

* Login
* Logout
* Customer creation
* Customer update
* KYC approval/rejection
* Car creation/update
* Document upload
* Auction creation
* Auction publication
* Auction modification
* Auction cancellation
* Auction start/end
* Participant approval/rejection
* Payment state change
* Inspection assignment
* Inspection status change
* Result finalization
* Staff/role changes

Audit record should contain:

* User
* Action
* Module/entity
* Entity ID
* Old values where appropriate
* New values where appropriate
* IP address
* User agent
* Timestamp

Audit logs should be append-only for normal administrators.

---

# 19. GLOBAL SEARCH & FILTERING

Filament resources should support useful search/filter functionality.

Search by:

* Customer name
* Email
* Mobile
* Car inventory ID
* Registration number
* Auction ID
* Auction title
* Payment ID
* Gateway transaction ID

Filters should be available for:

* Status
* Date
* Auction
* Customer
* Staff
* Payment status
* KYC status
* Inspection status

Use database-level filtering.

---

# 20. FILE & MEDIA MANAGEMENT

Implement secure file handling.

Files include:

* Car images
* Car documents
* KYC documents
* Inspection reports
* Inspection photographs

Requirements:

* Validate file types
* Validate file sizes
* Store files using Laravel filesystem
* Do not expose private documents through publicly guessable URLs
* Authorize access before serving protected files
* Keep file metadata in database where appropriate

---

# 21. DATABASE DESIGN

Create normalized database tables/models for at minimum:

* users
* roles
* permissions
* customers
* customer_kyc
* cars
* car_images
* car_documents
* locations
* inspections
* inspection_documents
* auctions
* auction_participants
* bids
* auction_results
* payments
* notifications
* audit_logs

Use proper:

* Foreign keys
* Indexes
* Unique constraints
* Cascading/restrictive relationships
* Soft deletes where appropriate
* Timestamps

Add indexes for frequently queried fields such as:

* auction_id
* customer_id
* car_id
* status
* payment status
* auction dates
* created_at

---

# 22. BUSINESS RULES

Implement these rules centrally in Services/Actions.

### Customer eligibility

Customer must have:

* Active account
* Approved KYC
* Valid auction participation
* Successful/authorized entry-fee payment

before participating in a live auction.

### Auction

An auction cannot become live unless:

* Car exists
* Car is eligible
* Auction configuration is complete
* Required inspection/inventory requirements are satisfied
* Auction has been published

### Bidding

A bid is valid only when:

* Auction is live
* Customer is eligible
* Bid amount meets the minimum increment
* Bid is processed atomically
* Customer is not blocked/suspended

### Result

Auction result must be finalized only once.

### Payment

Payment status changes must be controlled by the payment service/workflow.

Never directly update payment status from arbitrary Filament form fields.

---

# 23. FILAMENT IMPLEMENTATION

Create appropriate Filament:

* Resources
* Relation Managers
* Pages
* Widgets
* Actions
* Filters
* Tables
* Forms
* Infolists
* Notifications

Suggested resources:

* CustomerResource
* KycResource
* CarResource
* InspectionResource
* AuctionResource
* AuctionParticipantResource
* BidResource
* PaymentResource
* AuctionResultResource
* NotificationLogResource
* StaffUserResource
* AuditLogResource

Use Relation Managers where appropriate.

For example:

### Customer

Relations:

* KYC
* Auction Participations
* Payments
* Inspections
* Notifications
* Auction Results

### Car

Relations:

* Images
* Documents
* Inspections
* Auctions

### Auction

Relations:

* Participants
* Bids
* Payments
* Result
* Notifications

---

# 24. ADMIN UI/UX

The Filament panel should be operationally efficient.

Use:

* Clear navigation groups
* Meaningful icons
* Status badges
* Confirmation dialogs for destructive actions
* Bulk actions where safe
* Empty states
* Helpful validation messages
* Read-only fields when appropriate
* Appropriate date/time formatting
* Currency formatting in INR
* Responsive tables
* Pagination
* Loading states

Use the project brand where practical:

* Primary color: `#0098DB`
* White: `#FFFFFF`
* Typography/branding aligned with Poppins where supported

Do not sacrifice Filament usability for excessive customization.

---

# 25. SECURITY

Implement:

* Authorization policies
* Role/permission checks
* CSRF protection
* Validation
* Secure file access
* Rate limiting where appropriate
* Secure password handling
* Session security
* Audit logging
* Server-side business-rule validation

Never trust:

* Frontend values
* Hidden form fields
* Query parameters
* User-submitted role IDs
* User-submitted payment statuses
* User-submitted auction statuses

All sensitive state transitions must be authorized server-side.

---

# 26. TESTING

Create automated tests for critical business logic.

At minimum test:

### Customer/KYC

* Customer registration
* KYC approval
* KYC rejection
* Blocked customer cannot participate

### Auction

* Auction creation
* Auction publishing
* Invalid auction validation
* Auction lifecycle

### Participation

* Eligible customer can participate
* Non-KYC customer cannot participate
* Payment pending customer cannot participate

### Bidding

* Valid bid
* Invalid bid
* Bid below minimum increment
* Bid on closed auction
* Unauthorized customer bid

### Results

* Winner calculation
* Result finalization
* Duplicate result prevention

### Permissions

* Super Admin access
* Auction Manager restrictions
* Finance Manager restrictions
* Inspection Staff restrictions
* Customer Support restrictions

### Payments

* Payment state transitions
* Winner capture workflow
* Non-winner release/refund workflow

---

# 27. IMPLEMENTATION APPROACH

Implement this incrementally.

Recommended order:

1. Database architecture and migrations
2. Models and relationships
3. Roles and permissions
4. Customer/KYC module
5. Car/inventory module
6. Inspection module
7. Auction module
8. Participation module
9. Payment module
10. Bid module
11. Result/settlement module
12. Notification logs
13. Reports
14. Dashboard widgets
15. Audit logging
16. Security hardening
17. Automated tests
18. Final Filament UI polish

Before implementing each module:

* Check existing project structure.
* Reuse existing models/tables if present.
* Do not duplicate functionality.
* Do not overwrite existing working functionality unnecessarily.
* Create migrations for schema changes.
* Keep migrations reversible where practical.

---

# 28. IMPORTANT DEVELOPMENT RULES

Do not implement fake/demo functionality.

Do not hard-code database IDs.

Do not hard-code payment status changes.

Do not hard-code auction winners.

Do not bypass authorization.

Do not place major business logic inside Blade/Filament UI code.

Do not expose private KYC documents publicly.

Do not trust client-side validation.

Do not create unnecessary duplicate tables.

Use Laravel's dependency injection, validation, policies, events, jobs, queues, notifications, and database transactions appropriately.

For asynchronous operations such as notifications, reporting jobs, or external provider communication, use Laravel queues where appropriate.

Every critical workflow must be safe against duplicate execution.

---

# 29. EXPECTED RESULT

The final Filament Admin Panel should allow an authorized administrator to manage the complete operational lifecycle:

**Customer → KYC → Car → Inspection → Auction → Participation → Payment → Live Bidding Monitoring → Auction Result → Settlement → Reporting**

The implementation should be production-oriented, maintainable, secure, permission-aware, and ready for integration with the customer-facing applications and backend services later.

Do not implement customer mobile/web UI in this task.

Focus exclusively on the **Laravel + Filament Admin and Sub-Admin system**.
