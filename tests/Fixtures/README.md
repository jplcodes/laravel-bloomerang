# Fixtures

Response bodies shaped after Bloomerang's published REST API v2 OpenAPI specification
(`https://bloomerang-api-documentation.s3.us-west-2.amazonaws.com/public_crm_generated.json`,
linked from https://bloomerang.com/api/rest-api). Every name, email, id and date is made up.
Nothing here comes from a real Bloomerang account.

Where a live response is shaped differently from the specification, or carries more than it documents, the fixtures follow the observed shape, still with made-up values:

- The membership array is `Memberships`, not `Membership`.
- A constituent in a household carries `HouseholdId` (`constituent-in-household`).
- `PrimaryEmail` has its own `AuditTrail`, and can be missing altogether.
- Dates are `YYYY-MM-DDTHH:MM:SSZ`, except some nested audit trails (a phone's, for one) that leave off the `Z`.
- `user/current` also carries `IsSupportUser`, `PendingEmail`, `BccEmail`, `Phone`, `SendTaskEmails`, and `UserTimeZone`.
- Error responses (400, 401, 404) are `{Message, ErrorCode}` (`error-*`). No 429 body was observed, so tests reuse that shape for other statuses.
