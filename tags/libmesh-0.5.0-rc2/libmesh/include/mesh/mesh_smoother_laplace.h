// $Id: mesh_smoother_laplace.h,v 1.5 2005-02-22 22:17:33 jwpeterson Exp $

// The libMesh Finite Element Library.
// Copyright (C) 2002-2005  Benjamin S. Kirk, John W. Peterson
  
// This library is free software; you can redistribute it and/or
// modify it under the terms of the GNU Lesser General Public
// License as published by the Free Software Foundation; either
// version 2.1 of the License, or (at your option) any later version.
  
// This library is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
// Lesser General Public License for more details.
  
// You should have received a copy of the GNU Lesser General Public
// License along with this library; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA



#ifndef __mesh_smoother_laplace_h__
#define __mesh_smoother_laplace_h__



// C++ Includes   -----------------------------------


// forward declarations


// Local Includes -----------------------------------
#include "mesh_smoother.h"


/**
 * This class defines the data structures necessary
 * for Laplace smoothing.  Note that this is a simple
 * averaging smoother, which does NOT guarantee that
 * points will be smoothed to valid locations, e.g.
 * locations inside the boundary!  This aspect could
 * use work.
 *
 * \author John W. Peterson
 * \date 2002-2003
 * \version $Revision: 1.5 $
 */


// ------------------------------------------------------------
// LaplaceMeshSmoother class definition
class LaplaceMeshSmoother : public MeshSmoother
{
public:
  /**
   * Constructor.  Sets the constant mesh reference
   * in the protected data section of the class.
   */
  LaplaceMeshSmoother(Mesh& mesh)
    : MeshSmoother(mesh),
      _initialized(false) {}

  /**
   * Destructor.
   */
  virtual ~LaplaceMeshSmoother() {}

  /**
   * Redefinition of the smooth function from the
   * base class.  All this does is call the smooth
   * function in this class which takes an int, using
   * a default value of 1.
   */
  virtual void smooth() { this->smooth(1); }

  /**
   * The actual smoothing function, gets called whenever
   * the user specifies an actual number of smoothing
   * iterations.
   */
  void smooth(unsigned int n_iterations);
  
  /**
   * Initialization for the Laplace smoothing routine
   * is basically identical to building an "L-graph"
   * which is expensive.  It's provided separately from
   * the constructor since you may or may not want
   * to build the L-graph on construction.
   */
  void init();

  /**
   * Mainly for debugging, this function will print
   * out the connectivity graph which has been created.
   */
  void print_graph() const;
  
private:

  /**
   * True if the L-graph has been created, false otherwise.
   */
  bool _initialized;

  /**
   * Data structure for holding the L-graph
   */
  std::vector<std::vector<unsigned int> > _graph;
};


#endif
