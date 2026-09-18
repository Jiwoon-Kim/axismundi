# Draft — Gutenberg #83148 prototype pointer

## Body (GitHub Markdown — paste as is)

I opened [#83159](https://github.com/WordPress/gutenberg/pull/83159) as a draft implementation of this model. It is deliberately a design experiment, not a request for code review yet.

It includes the three layers in this issue: a face `axes` capability list, `settings.typography.fontVariations` as the theme policy, and `styles.typography.fontVariationSettings` as an object value. The UI offers only the policy-exposed axes, in a separate Font variations panel for Paragraph, Heading, and the corresponding Global Styles nodes.

The prepared [Playground demo](https://playground.wordpress.net/?gutenberg-pr=83159&blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2FJiwoon-Kim%2Faxismundi%2F93d994a%2Fdemos%2Fgutenberg-font-variations%2Fblueprint.json&storage=temp) installs the PR build and a Roboto Flex fixture, then opens the demo post in the editor.

The draft intentionally treats capability and policy as availability for the UI, not as sanitization: changing the font family on a style node clears that node's axis values; otherwise stored or inherited values remain intact, including values the current panel does not offer. The open questions in this issue remain the decisions needed before the PR is ready for review.
<!-- end of body -->
