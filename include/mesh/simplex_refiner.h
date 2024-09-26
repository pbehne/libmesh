// The libMesh Finite Element Library.
// Copyright (C) 2002-2024 Benjamin S. Kirk, John W. Peterson, Roy H. Stogner

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


#ifndef LIBMESH_SIMPLEX_REFINER_H
#define LIBMESH_SIMPLEX_REFINER_H

#include "libmesh/libmesh_config.h"

// Local Includes
#include "libmesh/elem.h"
#include "libmesh/function_base.h"

#include <map>
#include <memory>

namespace libMesh
{

// Forward Declarations
class UnstructuredMesh;
class Node;

/**
 * A C++ class to refine a simplicial mesh via splitting edges that
 * exceed a given metric.
 *
 * \author Roy H. Stogner
 * \date 2024
 */
class SimplexRefiner
{
public:
  /**
   * The constructor.  A reference to the mesh containing the elements
   * which are to be split by edge subdivision must be provided.
   *
   * At the time of refining this mesh should be conforming.
   */
  explicit
  SimplexRefiner(UnstructuredMesh & mesh);

  /**
   * Finds elements which exceed the requested metric and refines them
   * via subdivision into new simplices as necessary.
   *
   * \returns \p true iff the mesh actually changed.
   */
  bool refine_elements ();

  /**
   * Set a function giving desired element volume as a function of
   * position.  Set this to nullptr to disable position-dependent volume
   * constraint (falling back on desired_volume()).
   */
  virtual void set_desired_volume_function (FunctionBase<Real> * desired);

  /**
   * Get the function giving desired element volume as a function of
   * position, or \p nullptr if no such function has been set.
   */
  virtual FunctionBase<Real> * get_desired_volume_function ();

  /**
   * Sets and/or gets the desired element volume. Set to zero to disable
   * volume constraint.
   *
   * If a \p desired_volume_function is set, then \p desired_volume()
   * should be used to set a *minimum* desired volume; this will reduce
   * "false negatives" by suggesting how finely to sample \p
   * desired_volume_function inside large elements, where ideally the
   * \p desired_volume_function will be satisfied in the element
   * interior and not just at the element vertices.
   */
  Real & desired_volume() {return _desired_volume;}

protected:

  /**
   * Finds elements which exceed the requested metric and refines them
   * via inserting new midedge nodes and bisecting as necessary.
   *
   * \returns the number of refined elements
   */
  std::size_t refine_via_edges();

  /**
   * Checks if an element exceeds the requested metric
   */
  bool should_refine_elem(Elem & elem);

  /**
   * Checks if an element exceeds the requested metric or if it
   * has an edge which was split by a neighboring metric and refines
   * it (bisecting it, removing it from the mesh to be replaced by the
   * two subelements, and recursing into those) if necessary.
   *
   * \returns the number of refinements done; this may be greater than
   * 1 if subelements were themselves refined.
   */
 
  std::size_t refine_via_edges(Elem & elem);

private:

  /**
   * Reference to the mesh which is to be refined.
   */
  UnstructuredMesh & _mesh;

  /**
   * The desired volume for the elements in the resulting mesh.
   */
  Real _desired_volume;

  /**
   * Location-dependent volume requirements
   */
  std::unique_ptr<FunctionBase<Real>> _desired_volume_func;

  /**
   * Keep track of new nodes on edges.  A new node x in between node y
   * and node z (with y<z) will be reflected by new_nodes[(y,z)]=x
   */
  std::map<std::pair<Point *, Point *>, Node *> new_nodes;

  /**
   * Keep track of elements to add so we don't invalidate iterators
   * during an iteration over elements.
   */
  std::vector<std::unique_ptr<Elem>> new_elements;
};


} // namespace libMesh

#endif // ifndef LIBMESH_SIMPLEX_REFINER_H
