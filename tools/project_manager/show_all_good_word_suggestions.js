/*global $ splitControl */

$(function () {
    let mainSplit = splitControl("#suggestions_container", {
        splitVertical: true,
        splitPercent: 40,
    });
    mainSplit.reLayout();
});
