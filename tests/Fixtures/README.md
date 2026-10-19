# Fixtures

Response bodies shaped after Bloomerang's published REST API v2 OpenAPI specification
(`https://bloomerang-api-documentation.s3.us-west-2.amazonaws.com/public_crm_generated.json`,
linked from https://bloomerang.com/api/rest-api). Every name, email, id and date is made up.
Nothing here comes from a real Bloomerang account.

Where a live response is shaped differently from the specification, the fixtures follow the observed shape, still with made-up values: the membership array is `Memberships`, not `Membership`.

The specification documents no error bodies for these endpoints, so tests fake errors by status code only.
