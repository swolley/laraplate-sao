---
module: sao
audience: user
cross_cutting_user: false
---
# Connecting SAO to external systems — what to expect

SAO is a complete standalone tracker on its own. Connecting it to external systems — a version control host, a log or error source, another issue tracker — is **optional** and delivered as independent, switchable integrations called **drivers**. A driver is the code that knows how to talk to one kind of system; a **connection** is one configured instance of it (for example, your company's Redmine).

## What exists today

The connection framework exists, but **no external connector is available yet** — the first one arrives in a later release. Right now SAO works as a self-contained ticketing system with no connection configured, exactly as before.

## How connections will work

When connectors arrive, a superadmin will add a connection from the panel: pick the driver, fill in the form the driver describes (endpoints and any credentials), and use a **test** button to check reachability. A few things are true by design:

- **Your secrets stay secret.** A connection's credential is stored encrypted and is **write-only** — once saved it is never shown back on screen; you replace it rather than read it. Alternatively an administrator can point a connection at a credential kept in the server environment, so it can be rotated without touching the panel at all.
- **One connection, many capabilities.** A single connection can offer several capabilities at once (for instance a code host that also serves issues and releases). You only enable the capabilities you actually want that connection to expose.
- **Status names are yours to map.** Because every installation names its statuses differently, SAO maps an external status onto its own canonical meaning through a small, editable table with sensible defaults — never a fixed guess.

You do not need to configure anything to use SAO today; these options simply appear as the driver waves ship.
