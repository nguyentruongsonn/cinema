# Cinema API Contract

The OpenAPI 3.1 contract is generated from Laravel's registered runtime routes, preventing URI and HTTP method drift.

- JSON contract: `GET /api/v1/docs/openapi.json`
- API base URL: `/api/v1`
- Authentication: HttpOnly `access_token` cookie or JWT bearer token
- Errors include a safe `request_id` for log and Sentry correlation

`tests/Feature/OpenApiContractTest.php` verifies that every registered API route and method exists in the generated contract.
