#!/usr/bin/env python3
import matplotlib.pyplot as plt
import numpy as np
import argparse

"""
This script creates a bar graph of the number of "papers using libmesh" by year.

* We do not simply count citations, the authors must have actually
  used libmesh in part of their work.
* Conversely, not everyone who uses libmesh actually cites it, for
  example when it is used through another library or application. We
  also include such papers in this list.
* This data can be regenerated by running the ./create_website.sh
  script in the doc/citations directory.
* The yearly citations can include anything (conference papers,
  technical reports, etc.) that is not a dissertation/thesis, as those
  are counted in a separate category.
* The libmesh paper came out in 2006, but there are some citations
  prior to that date which mainly referred to the old libmesh.sf.net
  site at the time.
"""

# These counts can be generated from the latest Bibtex files by
# running "./create_website.sh -c" in the doc/citations
# directory. Note: you must have bibtex2html in your PATH in order to
# run this script.
data = [
'\'04', 7,
'\'05', 2,
'\'06', 15,
'\'07', 10,
'\'08', 30,
'\'09', 31,
'\'10', 30,
'\'11', 40,
'\'12', 57,
'\'13', 89,
'\'14', 82,
'\'15', 109,
'\'16', 145,
'\'17', 152,
'\'18', 159,
'\'19', 134,
'\'20', 190,
'\'21', 215,
'\'22', 239,
'\'23', 235,
'\'24', 243,
'\'25', 21,
    ]

# Parse command line args
parser = argparse.ArgumentParser()
parser.add_argument("--png", action='store_true', default=False)
args = parser.parse_args()

# Extract the x-axis labels from the data array
ticklabels = data[0::2]

# Extract the publication counts from the data array
n_papers = data[1::2]

# The number of data points
N = len(ticklabels);

# Get a reference to the figure
fig = plt.figure()

# 111 is equivalent to Matlab's subplot(1,1,1) command
ax = fig.add_subplot(111)

# Create an x-axis for plotting
x = np.linspace(1, N, N)

# Width of the bars
width = 0.8

# Make the bar chart.
# The colors used come from sns.color_palette("muted").as_hex() They
# are the "same basic order of hues as the default matplotlib color
# cycle but more attractive colors."
ax.bar(x[0:N], n_papers[0:N], width, color=u'#4878cf', align='center')

# Set up the xtick locations and labels.
ax.set_xticks(x[::2]) # place ticks at every other element of x
ax.set_xticklabels(ticklabels[::2])
ax.tick_params(direction='out')
ax.set_xlim([0,N+1])

# Create a title string
title_string = 'Papers by People Using LibMesh, (' + str(sum(n_papers)) + ' Total)'
fig.suptitle(title_string)

# Write to PDF unless script was run with --png option.
# On Ubuntu, you may need to run the following
# command to get this working:
# sudo apt-get install dvipng
# To subsequently update the website,
# cp *.png ~/projects/libMesh.github.io/images/
# and then push the changes.
if args.png:
  plt.savefig('libmesh_citations.png', format='png', dpi=200)
else:
  plt.savefig('libmesh_citations.pdf')

# Local Variables:
# python-indent: 2
# End:
