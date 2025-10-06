# Changesets

This project uses [Changesets](https://github.com/changesets/changesets) for versioning and generating changelogs in the repo.

To generate a Changeset (_copied and modified from [Changesets' docs](https://github.com/changesets/changesets/blob/01c037c0462540196b5d3d0c0241d8752b465b4b/docs/adding-a-changeset.md)_):

1. Run `npm run changeset` in the root of the OneMedia repo.
2. You will be prompted to select a bump type. Select a **Major**, **Minor**, or **Patch** bump.

    - **Major**: Any form of breaking change.
    - **Minor**: New (non-breaking) features or changes.
    - **Patch**: Bug fixes.

3. Your final prompt will be to provide a message to go along with the changeset. This message will be written to the changeset when the next release is made.

   > ⚠️ **Important**
   >
   > Remember to follow [Conventional Commits formatting](https://www.conventionalcommits.org/en/v1.0.0/) and to use imperative language in your changeset message.
   >
   > For example, "feat: Add new feature" instead of "Added new feature".

   After this, a new changeset will be added which is a markdown file with YAML front matter.

    ```bash
    -| .changeset/
    -|-| UNIQUE_ID.md
    ```

   The message you typed can be found in the markdown file. If you want to expand on it, you can write as much markdown as you want, which will all be added to the changelog on publish. You can also rename the markdown file with a more suitable name if needed.

4. Once you are happy with the changeset, commit the file to your branch.
