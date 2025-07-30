# jb-deduplication
A WordPress plugin which adds WP_CLI commands that search for, and delete, duplicate posts.

The purpose of this plugin is to allow users to quickly remove duplicate posts which meet certain criteria.

The use case here was to remove two specific types of posts:
- Duplicated attachment posts which were a PDF file
- Document Library Pro posts (dlp_document) which either had an invalid PDF file attached to it, or was a duplicate of another post.

The deduplication process is achieved by creating two WP CLI commands:
- To remove duplicate PDF files, the command is "wp pdf-media-dedup"
- To remove DLP Document posts, the command is "wp dlp-document-dedup"

## Arguments

Each command has a few arguments which are laid out in the Class doc block. Here are some explanations of the arguments which are common to both commands

"--dry-run"
Allows you to run the command and log which posts would be deleted without actually deleting anything

"--batch-size={int}"
Allows you to set the number of posts which will be processed by the command

"--start-post-id={int}"
The ID of the post which you want to start the batch with. DLP Document posts are processed in descending order while PDF attachment posts are processed in ascending order.

If a start post ID is not included, the script will pull either the ID of the last post which was recently run, or a deafult start post ID. In the case of PDF attachements, the default ID is 1. In the case of DLP Document deduplication, the default post ID is the highest post ID for the "dlp_document" post type.

"--skip-confirmations"
By default, there are confirmations for each duplicated post which is found. If you want to skip action confirmations, then this will process the actions for each post without your input. 

## Logging

The scripts create CSV files of each batch run to record the duplicate posts which are found and/or deleted. The CSV files are stored within the "logs" subdirectory of the plugin. There are three commands to delete the logs in this directory:
- "wp pdf-media-dedup-delete-logs"
- "wp dlp-document-dedup-delete-logs"
- "wp dlp-document-missing-pdf-delete-logs"

## Recommended Use

It is recommended to run the PDF Attachment deduplication script first. This way, the existing PDF files are already cleaned up. This makes the search for DLP Document posts with invalid PDF files more accurate.

## Questions, comments, concerns?
File an issue or reach out at https://jessboctor.com/contact/