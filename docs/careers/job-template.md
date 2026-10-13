---
# StudyVibe job posting template.
# Fill in the values after the colons, then upload this file in the careers workspace (Positions > Import from Markdown).
# You will see a preview with every problem listed before anything is published.
# Lines starting with # inside this block, and anything between <!-- and --> anywhere, are ignored.

title: Senior Backend Engineer
# One of: engineering, education, product, sales, success, marketing, data, operations, people
team: engineering
location: Douala, Cameroon
# One of: onsite, hybrid, remote
work_mode: hybrid
# One of: full_time, part_time, internship, contract, volunteer
type: full_time
# Free text: Junior, Mid-level, Senior, Lead ... (optional)
level: Senior
openings: 1
# Last day to apply, YYYY-MM-DD (optional, must be in the future)
closes: 2026-12-31
# Free text, shown as it is (optional)
salary: To be discussed with shortlisted candidates
# 4 letters or digits that start every application reference, for example SBEN (optional: generated from the title when empty)
code:
# open = published when you approve, draft = saved privately
status: open

# Automatic screening of applications. on or off.
screening: on
# Applications scoring below this (10 to 80) are held in the Screening section for you to review. Nothing is refused automatically.
screening_threshold: 35
# 0 = never. Otherwise a held-back application nobody reviewed gets a polite decline after this many days.
auto_decline_days: 0
# Minimum years of experience (0 = no requirement)
min_years: 5
# One of: highschool, associate, bachelor, master, doctorate (empty = no requirement)
min_education: bachelor
---

## Summary

One or two sentences, 20 to 400 characters. This is the text on the list of open positions.

## About the role

Write paragraphs here. Separate paragraphs with a blank line. Say what the team does, why the role exists and
what success looks like. Maximum 6,000 characters.

## Responsibilities

- One concrete responsibility per line, starting with a verb.
- Up to 25 lines, 300 characters each.
- A line that is longer than your editor's width can continue on the next line if it is indented by two spaces.

## Requirements

- What a candidate must have. One requirement per line.
- Be precise: say "5 years of PHP", not "good experience".

## Nice to have

- Things that help but are not required.

## Benefits

- What the position offers. One benefit per line.

## Screening

### Must have

<!-- One keyword per line. A "!" in front makes it a knock-out: an application without it is held back whatever its score.
     "a|b" means either word is enough. Leave this list empty and the keywords are derived from the requirements. -->

- !php
- mysql|mariadb
- javascript

### Nice to have

- docker
- accessibility|wcag
