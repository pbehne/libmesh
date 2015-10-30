// $Id: linear.h,v 1.3 2005-02-22 22:17:35 jwpeterson Exp $

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



#ifndef __linear_h__
#define __linear_h__

// C++ includes

// Local includes
#include "libmesh_common.h"
#include "solver.h"


/**
 * This is a generic class that defines a linear to be used in a
 * simulation.  A user can define a linear by deriving from this
 * class and implementing certain functions.
 *
 * @author Benjamin S. Kirk, 2003-2004.
 */

// ------------------------------------------------------------
// Linear class definition

template <class T = Solver>
class Linear : public T
{
public:
  
  /**
   * Constructor. Requires a reference to a system to be solved.
   */
  Linear (EquationSystems& es);

  /**
   * Constructor.  Requires a referece to the \p EquationSystems object.
   */
  Linear (EquationSystems& es,
	  const std::string& name,
	  const unsigned int number);
  
  /**
   * Destructor.
   */
  ~Linear ();
};



// ------------------------------------------------------------
// Linear inline members
template <class T>
inline
Linear<T>::Linear(EquationSystems& es) : 
  T (es)
{
}



template <class T>
inline
Linear<T>::Linear (EquationSystems& es,
		   const std::string& name,
		   const unsigned int number) :
  T (es, name, number)
{
}



template <class T>
inline
Linear<T>::~Linear ()
{
}



#endif // #define __linear_h__
