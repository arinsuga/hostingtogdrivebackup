---
name: generate-commit-message
description: Generate a commit message for the given changes.
---

# Generate Commit Message Skill

The golden rule of Git commit messages is clarity: each commit should be in plain text and represent a single logical change, with a concise subject line and optional detailed body explaining the “why” behind the change. Following structured conventions makes collaboration, debugging, and project history far easier.


## Structure

- Subject line (mandatory):
- Keep it ≤ 50 characters.
- Use the imperative mood (e.g., “Add login validation” not “Added” or “Adding”).
- Capitalize the first word.
- Do not end with a period.
- Blank line between subject and body.
- Body (optional but recommended):
- Explain why the change was made, not just what.
- Wrap lines at 72 characters for readability in tools.
- Include references to issues/tickets if relevant.

## Content Guidelines

- read the example from .agent/skills/git/generate-commit-message/format-example.md
- Atomic commits: Each commit should represent one logical change (e.g., fix a bug, add a feature, update docs).
- Be descriptive but concise: Avoid vague messages like “fix stuff” or “update code.”
- Explain reasoning: If the change isn’t obvious, document the motivation.
- Reference context: Link to issue numbers or PRs when applicable.
- Explain every file added, modified, or deleted and split into categories between frontend and backend or others. Give only the file name and the type of change (added, modified, or deleted) exclude information about fullpath of the file.
- Generate only plain text format.
- make sure to display the plain text and able to copy it to terminal!

## Conventional Commit Standards (Optional but Popular)

Adopt the Conventional Commits format for consistency:

<branch-name> - <type>(optional scope): <subject>

**Examples:**
- feat(auth): add JWT-based login
- fix(ui): correct button alignment
- docs(readme): update installation steps
**Common types:**
- feat → new feature
- fix → bug fix
- docs → documentation changes
- style → formatting, no code logic changes
- refactor → code restructuring without behavior change
- test → adding or updating tests
- chore → maintenance tasks (build, CI, dependencies)

## Best Practices

- Write commits as if explaining to future developers.
- Avoid bundling unrelated changes.
- Use present tense, imperative mood.
- Test before committing to ensure the commit is valid and complete.
- Review commit history to maintain a clean, understandable log.

## Additional Information

- Give information for all files that have been added, modified, or deleted and split into categories between frontend and backend or others. Give only the file name and the type of change (added, modified, or deleted) exclude information about fullpath of the file.