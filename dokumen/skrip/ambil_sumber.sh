#!/bin/sh
# Unduh DOCX sumber dari Google Drive (ekspor publik) ke /tmp/sumber.
set -e
mkdir -p /tmp/sumber
curl -sL -o /tmp/sumber/SKPL-LelangIkan-v1.0-docx.docx \
  "https://docs.google.com/document/d/1SczsjBflcmrekCeJYchtNG4KNa6_WL3W/export?format=docx"
curl -sL -o /tmp/sumber/DPPL-LelangIkan-v1.0-docx.docx \
  "https://docs.google.com/document/d/19-SRrEuc1VdDeMd5cFCganLKXCrT48u6/export?format=docx"
ls -lh /tmp/sumber
