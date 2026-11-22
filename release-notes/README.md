# Release notes

The notes the Nextcloud App Store shows for a release are written by hand, here. The repo's
`CHANGELOG.md` and the GitHub release keep release-please's full log.

- `next/<slug>.md`: one fragment per change people will notice, committed together with its `feat`
  or `fix` commit.
- `<version>/`: the notes for a released version: `appstore.md`, plus `full.md`, which holds every
  fragment of the release.
- `config.yml`: the destinations and the platforms that reach them.

A fragment:

```md
---
kind: fix # feat | fix
area: shopping # changes in the same area are grouped together
platforms: [all]
weight: notable # headline | notable | minor
onboarding: no # yes | no | maybe
onboarding_reason: # required when onboarding is yes
---

Totals on a shopping trip now count every item's quantity, so the estimate matches what you pay at
the till.
```

Once the release PR is open, the fragments in `next/` are written up into `<version>/` and removed
from `next/`. The release PR can't merge until that folder is complete, and a release pushed without
it is not published to the App Store. `make appstore` writes every version's `appstore.md` into the
tarball's `CHANGELOG.md`, which is what the App Store displays.
