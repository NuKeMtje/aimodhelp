# New Extension Active: AI Assistant for Forum Posts

Starting today, a new extension is active on the forum that adds AI functionality for forum posts. At the top of each post, an extra button appears that allows you to request a summary of the post itself or a part of the thread. Moderators also receive additional tools to check responses on a topic against the code of conduct. This is intended to make their work easier and more efficient.

## About the Development

The extension was developed by Nukem (lead developer), EOTT (styling), and Goztow (extension integration), with support from AI coding tools. After a testing phase of several weeks, we decided to integrate AI assistance on Userbase — in a way that does not harm the quality of human interaction, but rather supports it where useful.

We believe that Userbase is taking a particularly innovative step in the Flemish forum environment with this. It is possible that we will expand the functionality further based on constructive ideas and community feedback.

## How Does It Work?

Click on the list icon next to the thumbs-up at the top right of a post. A pop-up window will open with the AI output. This works entirely in the browser without needing to reload the page.

The tool uses a language model that displays automatically generated content. Results are indicative and may contain errors.

Currently, we use a free large language model (LLM), namely `microsoft/mai-ds-r1`. This means availability is not always guaranteed and the data sent may be used for the further training of this LLM or other models.

We take privacy seriously at Userbase and have carefully considered this trade-off. Previous tests show that some LLMs already contain data from Userbase and that public website data is used for training anyway. From this perspective, this tool does not change much.

## Why?

The main motivation behind this extension is to support moderation work on the forum. Userbase runs on motivated volunteers, but even they cannot possibly systematically read through all topics or completely review complex discussions to filter out rule violations and then formulate a nuanced response.

Our experience with current LLMs is that they are not perfect but certainly good enough to support this task! The final decision to post a reply or take a measure (whether or not supported by a clear analysis from the tool with references) always remains with the moderators, but they can now act more easily and quickly thanks to this support.

At the same time, it was a small step to also offer registered users more functionality in the form of a **‘summarize’** button. Non-moderators can summarize up to the last 20 posts. We also chose not to make moderator assistance available to users to prevent misuse in certain discussions. If you want to report a post that needs moderator attention, you can find the **‘report’** button above the relevant post, not far from the **‘summarize’** button.

## What Does the Future Hold?

We are confident that the already good results will only improve as LLMs become more refined.

Additionally, we are considering expanding the functionality with periodic reporting. This would analyze all posts and topics over a certain period to identify outliers, users requiring extra attention, or recurring conflicts between the same participants.

By combining multiple such reports, we can gain valuable long-term insights and better substantiate existing suspicions. This enables us to respond more adequately where needed.

Another option we may implement is anonymizing posts before sending them to the LLM and then de-anonymizing the response afterward so it remains usable for us. However, the question is whether all this is necessary.

## Experiences and Feedback Welcome

This extension is an internal development by the Userbase team. We are still in a test phase: feedback, bug reports, or suggestions are very welcome. Let us know — then we will improve step by step.

Suggestions for other types of output are also welcome.
