<?php $root=""; ?>
<?php require($root."navigation.php"); ?>
<html>
<head>
  <?php load_style($root); ?>
</head>
 
<body>
 
<?php make_navigation("ex19",$root)?>
 
<div class="content">
<a name="comments"></a> 
<div class = "comment">
<h1>Example 19 - Solving the 2D Young Laplace Problem using nonlinear solvers</h1>

<br><br>This example shows how to use the NonlinearImplicitSystem class
to efficiently solve nonlinear problems in parallel.

<br><br>In nonlinear systems, we aim at finding x that satisfy R(x) = 0. 
In nonlinear finite element analysis, the residual is typically 
of the form R(x) = K(x)*x - f, with K(x) the system matrix and f 
the "right-hand-side". The NonlinearImplicitSystem class expects  
two callback functions to compute the residual R and its Jacobian 
for the Newton iterations. Here, we just approximate 
the true Jacobian by K(x).

<br><br>You can turn on preconditining of the matrix free system using the
jacobian by passing "-pre" on the command line.  Currently this only
work with Petsc so this isn't used by using "make run"

<br><br>This example also runs with the experimental Trilinos NOX solvers by specifying 
the --use-trilinos command line argument.
 

<br><br>

<br><br>C++ include files that we need
</div>

<div class ="fragment">
<pre>
        #include &lt;iostream&gt;
        #include &lt;algorithm&gt;
        #include &lt;cmath&gt;
        
</pre>
</div>
<div class = "comment">
Various include files needed for the mesh & solver functionality.
</div>

<div class ="fragment">
<pre>
        #include "libmesh.h"
        #include "mesh.h"
        #include "mesh_refinement.h"
        #include "exodusII_io.h"
        #include "equation_systems.h"
        #include "fe.h"
        #include "quadrature_gauss.h"
        #include "dof_map.h"
        #include "sparse_matrix.h"
        #include "numeric_vector.h"
        #include "dense_matrix.h"
        #include "dense_vector.h"
        #include "elem.h"
        #include "string_to_enum.h"
        #include "getpot.h"
        
</pre>
</div>
<div class = "comment">
The nonlinear solver and system we will be using
</div>

<div class ="fragment">
<pre>
        #include "nonlinear_solver.h"
        #include "nonlinear_implicit_system.h"
        
</pre>
</div>
<div class = "comment">
Necessary for programmatically setting petsc options
</div>

<div class ="fragment">
<pre>
        #ifdef LIBMESH_HAVE_PETSC
        #include &lt;petsc.h&gt;
        #endif
        
</pre>
</div>
<div class = "comment">
Bring in everything from the libMesh namespace
</div>

<div class ="fragment">
<pre>
        using namespace libMesh;
        
</pre>
</div>
<div class = "comment">
A reference to our equation system
</div>

<div class ="fragment">
<pre>
        EquationSystems *_equation_system = NULL;
        
</pre>
</div>
<div class = "comment">
Let-s define the physical parameters of the equation
</div>

<div class ="fragment">
<pre>
        const Real kappa = 1.;
        const Real sigma = 0.2;
        
        
</pre>
</div>
<div class = "comment">
This function computes the Jacobian K(x)
</div>

<div class ="fragment">
<pre>
        void compute_jacobian (const NumericVector&lt;Number&gt;& soln,
                               SparseMatrix&lt;Number&gt;&  jacobian,
                               NonlinearImplicitSystem& sys)
        {
</pre>
</div>
<div class = "comment">
Get a reference to the equation system.
</div>

<div class ="fragment">
<pre>
          EquationSystems &es = *_equation_system;
        
</pre>
</div>
<div class = "comment">
Get a constant reference to the mesh object.
</div>

<div class ="fragment">
<pre>
          const MeshBase& mesh = es.get_mesh();
        
</pre>
</div>
<div class = "comment">
The dimension that we are running
</div>

<div class ="fragment">
<pre>
          const unsigned int dim = mesh.mesh_dimension();
        
</pre>
</div>
<div class = "comment">
Get a reference to the NonlinearImplicitSystem we are solving
</div>

<div class ="fragment">
<pre>
          NonlinearImplicitSystem& system = 
            es.get_system&lt;NonlinearImplicitSystem&gt;("Laplace-Young");
          
</pre>
</div>
<div class = "comment">
A reference to the \p DofMap object for this system.  The \p DofMap
object handles the index translation from node and element numbers
to degree of freedom numbers.  We will talk more about the \p DofMap
in future examples.
</div>

<div class ="fragment">
<pre>
          const DofMap& dof_map = system.get_dof_map();
        
</pre>
</div>
<div class = "comment">
Get a constant reference to the Finite Element type
for the first (and only) variable in the system.
</div>

<div class ="fragment">
<pre>
          FEType fe_type = dof_map.variable_type(0);
        
</pre>
</div>
<div class = "comment">
Build a Finite Element object of the specified type.  Since the
\p FEBase::build() member dynamically creates memory we will
store the object as an \p AutoPtr<FEBase>.  This can be thought
of as a pointer that will clean up after itself.
</div>

<div class ="fragment">
<pre>
          AutoPtr&lt;FEBase&gt; fe (FEBase::build(dim, fe_type));
          
</pre>
</div>
<div class = "comment">
A 5th order Gauss quadrature rule for numerical integration.
</div>

<div class ="fragment">
<pre>
          QGauss qrule (dim, FIFTH);
        
</pre>
</div>
<div class = "comment">
Tell the finite element object to use our quadrature rule.
</div>

<div class ="fragment">
<pre>
          fe-&gt;attach_quadrature_rule (&qrule);
        
</pre>
</div>
<div class = "comment">
Here we define some references to cell-specific data that
will be used to assemble the linear system.
We begin with the element Jacobian * quadrature weight at each
integration point.   
</div>

<div class ="fragment">
<pre>
          const std::vector&lt;Real&gt;& JxW = fe-&gt;get_JxW();
        
</pre>
</div>
<div class = "comment">
The element shape functions evaluated at the quadrature points.
</div>

<div class ="fragment">
<pre>
          const std::vector&lt;std::vector&lt;Real&gt; &gt;& phi = fe-&gt;get_phi();
          
</pre>
</div>
<div class = "comment">
The element shape function gradients evaluated at the quadrature
points.
</div>

<div class ="fragment">
<pre>
          const std::vector&lt;std::vector&lt;RealGradient&gt; &gt;& dphi = fe-&gt;get_dphi();
        
</pre>
</div>
<div class = "comment">
Define data structures to contain the Jacobian element matrix.
Following basic finite element terminology we will denote these
"Ke". More detail is in example 3.
</div>

<div class ="fragment">
<pre>
          DenseMatrix&lt;Number&gt; Ke;
        
</pre>
</div>
<div class = "comment">
This vector will hold the degree of freedom indices for
the element.  These define where in the global system
the element degrees of freedom get mapped.
</div>

<div class ="fragment">
<pre>
          std::vector&lt;unsigned int&gt; dof_indices;
        
</pre>
</div>
<div class = "comment">
Now we will loop over all the active elements in the mesh which
are local to this processor.
We will compute the element Jacobian contribution.
</div>

<div class ="fragment">
<pre>
          MeshBase::const_element_iterator       el     = mesh.active_local_elements_begin();
          const MeshBase::const_element_iterator end_el = mesh.active_local_elements_end();
        
          for ( ; el != end_el; ++el)
            {
</pre>
</div>
<div class = "comment">
Store a pointer to the element we are currently
working on.  This allows for nicer syntax later.
</div>

<div class ="fragment">
<pre>
              const Elem* elem = *el;
        
</pre>
</div>
<div class = "comment">
Get the degree of freedom indices for the
current element.  These define where in the global
matrix and right-hand-side this element will
contribute to.
</div>

<div class ="fragment">
<pre>
              dof_map.dof_indices (elem, dof_indices);
        
</pre>
</div>
<div class = "comment">
Compute the element-specific data for the current
element.  This involves computing the location of the
quadrature points (q_point) and the shape functions
(phi, dphi) for the current element.
</div>

<div class ="fragment">
<pre>
              fe-&gt;reinit (elem);
        
</pre>
</div>
<div class = "comment">
Zero the element Jacobian before
summing them.  We use the resize member here because
the number of degrees of freedom might have changed from
the last element.  Note that this will be the case if the
element type is different (i.e. the last element was a
triangle, now we are on a quadrilateral).
</div>

<div class ="fragment">
<pre>
              Ke.resize (dof_indices.size(),
                         dof_indices.size());
                   
</pre>
</div>
<div class = "comment">
Now we will build the element Jacobian.  This involves
a double loop to integrate the test funcions (i) against
the trial functions (j). Note that the Jacobian depends
on the current solution x, which we access using the soln
vector.

<br><br></div>

<div class ="fragment">
<pre>
              for (unsigned int qp=0; qp&lt;qrule.n_points(); qp++)
                {
                  Gradient grad_u;
            
                  for (unsigned int i=0; i&lt;phi.size(); i++)
                    grad_u += dphi[i][qp]*soln(dof_indices[i]);
                  
                  const Number K = 1./std::sqrt(1. + grad_u*grad_u);
                  
                  for (unsigned int i=0; i&lt;phi.size(); i++)
                    for (unsigned int j=0; j&lt;phi.size(); j++)
                      Ke(i,j) += JxW[qp]*(
                                          K*(dphi[i][qp]*dphi[j][qp]) +
                                          kappa*phi[i][qp]*phi[j][qp]
                                          );
                }
              
              dof_map.constrain_element_matrix (Ke, dof_indices);
              
</pre>
</div>
<div class = "comment">
Add the element matrix to the system Jacobian.
</div>

<div class ="fragment">
<pre>
              jacobian.add_matrix (Ke, dof_indices);
            }
        
</pre>
</div>
<div class = "comment">
That's it.
</div>

<div class ="fragment">
<pre>
        }
        
        
</pre>
</div>
<div class = "comment">
Here we compute the residual R(x) = K(x)*x - f. The current solution
x is passed in the soln vector
</div>

<div class ="fragment">
<pre>
        void compute_residual (const NumericVector&lt;Number&gt;& soln,
                               NumericVector&lt;Number&gt;& residual,
                               NonlinearImplicitSystem& sys)
        {
          EquationSystems &es = *_equation_system;
        
</pre>
</div>
<div class = "comment">
Get a constant reference to the mesh object.
</div>

<div class ="fragment">
<pre>
          const MeshBase& mesh = es.get_mesh();
        
</pre>
</div>
<div class = "comment">
The dimension that we are running
</div>

<div class ="fragment">
<pre>
          const unsigned int dim = mesh.mesh_dimension();
          libmesh_assert (dim == 2);
        
</pre>
</div>
<div class = "comment">
Get a reference to the NonlinearImplicitSystem we are solving
</div>

<div class ="fragment">
<pre>
          NonlinearImplicitSystem& system = 
            es.get_system&lt;NonlinearImplicitSystem&gt;("Laplace-Young");
          
</pre>
</div>
<div class = "comment">
A reference to the \p DofMap object for this system.  The \p DofMap
object handles the index translation from node and element numbers
to degree of freedom numbers.  We will talk more about the \p DofMap
in future examples.
</div>

<div class ="fragment">
<pre>
          const DofMap& dof_map = system.get_dof_map();
        
</pre>
</div>
<div class = "comment">
Get a constant reference to the Finite Element type
for the first (and only) variable in the system.
</div>

<div class ="fragment">
<pre>
          FEType fe_type = dof_map.variable_type(0);
        
</pre>
</div>
<div class = "comment">
Build a Finite Element object of the specified type.  Since the
\p FEBase::build() member dynamically creates memory we will
store the object as an \p AutoPtr<FEBase>.  This can be thought
of as a pointer that will clean up after itself.
</div>

<div class ="fragment">
<pre>
          AutoPtr&lt;FEBase&gt; fe (FEBase::build(dim, fe_type));
          
</pre>
</div>
<div class = "comment">
A 5th order Gauss quadrature rule for numerical integration.
</div>

<div class ="fragment">
<pre>
          QGauss qrule (dim, FIFTH);
        
</pre>
</div>
<div class = "comment">
Tell the finite element object to use our quadrature rule.
</div>

<div class ="fragment">
<pre>
          fe-&gt;attach_quadrature_rule (&qrule);
        
</pre>
</div>
<div class = "comment">
Declare a special finite element object for
boundary integration.
</div>

<div class ="fragment">
<pre>
          AutoPtr&lt;FEBase&gt; fe_face (FEBase::build(dim, fe_type));
                      
</pre>
</div>
<div class = "comment">
Boundary integration requires one quadraure rule,
with dimensionality one less than the dimensionality
of the element.
</div>

<div class ="fragment">
<pre>
          QGauss qface(dim-1, FIFTH);
          
</pre>
</div>
<div class = "comment">
Tell the finte element object to use our
quadrature rule.
</div>

<div class ="fragment">
<pre>
          fe_face-&gt;attach_quadrature_rule (&qface);
        
</pre>
</div>
<div class = "comment">
Here we define some references to cell-specific data that
will be used to assemble the linear system.
We begin with the element Jacobian * quadrature weight at each
integration point.   
</div>

<div class ="fragment">
<pre>
          const std::vector&lt;Real&gt;& JxW = fe-&gt;get_JxW();
        
</pre>
</div>
<div class = "comment">
The element shape functions evaluated at the quadrature points.
</div>

<div class ="fragment">
<pre>
          const std::vector&lt;std::vector&lt;Real&gt; &gt;& phi = fe-&gt;get_phi();
          
</pre>
</div>
<div class = "comment">
The element shape function gradients evaluated at the quadrature
points.
</div>

<div class ="fragment">
<pre>
          const std::vector&lt;std::vector&lt;RealGradient&gt; &gt;& dphi = fe-&gt;get_dphi();
        
</pre>
</div>
<div class = "comment">
Define data structures to contain the resdual contributions
</div>

<div class ="fragment">
<pre>
          DenseVector&lt;Number&gt; Re;
        
</pre>
</div>
<div class = "comment">
This vector will hold the degree of freedom indices for
the element.  These define where in the global system
the element degrees of freedom get mapped.
</div>

<div class ="fragment">
<pre>
          std::vector&lt;unsigned int&gt; dof_indices;
        
</pre>
</div>
<div class = "comment">
Now we will loop over all the active elements in the mesh which
are local to this processor.
We will compute the element residual.
</div>

<div class ="fragment">
<pre>
          residual.zero();
        
          MeshBase::const_element_iterator       el     = mesh.active_local_elements_begin();
          const MeshBase::const_element_iterator end_el = mesh.active_local_elements_end();
        
          for ( ; el != end_el; ++el)
            {
</pre>
</div>
<div class = "comment">
Store a pointer to the element we are currently
working on.  This allows for nicer syntax later.
</div>

<div class ="fragment">
<pre>
              const Elem* elem = *el;
        
</pre>
</div>
<div class = "comment">
Get the degree of freedom indices for the
current element.  These define where in the global
matrix and right-hand-side this element will
contribute to.
</div>

<div class ="fragment">
<pre>
              dof_map.dof_indices (elem, dof_indices);
        
</pre>
</div>
<div class = "comment">
Compute the element-specific data for the current
element.  This involves computing the location of the
quadrature points (q_point) and the shape functions
(phi, dphi) for the current element.
</div>

<div class ="fragment">
<pre>
              fe-&gt;reinit (elem);
        
</pre>
</div>
<div class = "comment">
We use the resize member here because
the number of degrees of freedom might have changed from
the last element.  Note that this will be the case if the
element type is different (i.e. the last element was a
triangle, now we are on a quadrilateral).
</div>

<div class ="fragment">
<pre>
              Re.resize (dof_indices.size());
              
</pre>
</div>
<div class = "comment">
Now we will build the residual. This involves
the construction of the matrix K and multiplication of it
with the current solution x. We rearrange this into two loops: 
In the first, we calculate only the contribution of  
K_ij*x_j which is independent of the row i. In the second loops,
we multiply with the row-dependent part and add it to the element
residual.


<br><br></div>

<div class ="fragment">
<pre>
              for (unsigned int qp=0; qp&lt;qrule.n_points(); qp++)
                {
                  Number u = 0;
                  Gradient grad_u;
                  
                  for (unsigned int j=0; j&lt;phi.size(); j++)
                    {
                      u      += phi[j][qp]*soln(dof_indices[j]);
                      grad_u += dphi[j][qp]*soln(dof_indices[j]);
                    }
                  
                  const Number K = 1./std::sqrt(1. + grad_u*grad_u);
                  
                  for (unsigned int i=0; i&lt;phi.size(); i++)
                    Re(i) += JxW[qp]*(
                                      K*(dphi[i][qp]*grad_u) +
                                      kappa*phi[i][qp]*u
                                      );
                }
        
</pre>
</div>
<div class = "comment">
At this point the interior element integration has
been completed.  However, we have not yet addressed
boundary conditions.
      

<br><br>The following loops over the sides of the element.
If the element has no neighbor on a side then that
side MUST live on a boundary of the domain.
</div>

<div class ="fragment">
<pre>
              for (unsigned int side=0; side&lt;elem-&gt;n_sides(); side++)
                if (elem-&gt;neighbor(side) == NULL)
                  {
</pre>
</div>
<div class = "comment">
The value of the shape functions at the quadrature
points.
</div>

<div class ="fragment">
<pre>
                    const std::vector&lt;std::vector&lt;Real&gt; &gt;&  phi_face = fe_face-&gt;get_phi();
        
</pre>
</div>
<div class = "comment">
The Jacobian * Quadrature Weight at the quadrature
points on the face.
</div>

<div class ="fragment">
<pre>
                    const std::vector&lt;Real&gt;& JxW_face = fe_face-&gt;get_JxW();
        
</pre>
</div>
<div class = "comment">
Compute the shape function values on the element face.
</div>

<div class ="fragment">
<pre>
                    fe_face-&gt;reinit(elem, side);
        
</pre>
</div>
<div class = "comment">
Loop over the face quadrature points for integration.
</div>

<div class ="fragment">
<pre>
                    for (unsigned int qp=0; qp&lt;qface.n_points(); qp++)
                      {
</pre>
</div>
<div class = "comment">
This is the right-hand-side contribution (f),
which has to be subtracted from the current residual
</div>

<div class ="fragment">
<pre>
                        for (unsigned int i=0; i&lt;phi_face.size(); i++)
                          Re(i) -= JxW_face[qp]*sigma*phi_face[i][qp];
                      } 
                  }
              
              dof_map.constrain_element_vector (Re, dof_indices);
              residual.add_vector (Re, dof_indices);
            }
        
</pre>
</div>
<div class = "comment">
That's it.  
</div>

<div class ="fragment">
<pre>
        }
        
        
        
</pre>
</div>
<div class = "comment">
Begin the main program.
</div>

<div class ="fragment">
<pre>
        int main (int argc, char** argv)
        {
</pre>
</div>
<div class = "comment">
Initialize libMesh and any dependent libaries, like in example 2.
</div>

<div class ="fragment">
<pre>
          LibMeshInit init (argc, argv);
        
        #if !defined(LIBMESH_HAVE_PETSC) && !defined(LIBMESH_HAVE_TRILINOS)
          if (libMesh::processor_id() == 0)
            std::cerr &lt;&lt; "ERROR: This example requires libMesh to be\n"
                      &lt;&lt; "compiled with nonlinear solver support from\n"
                      &lt;&lt; "PETSc or Trilinos!"
                      &lt;&lt; std::endl;
          return 0;
        #endif
        
        #ifndef LIBMESH_ENABLE_AMR
          if (libMesh::processor_id() == 0)
            std::cerr &lt;&lt; "ERROR: This example requires libMesh to be\n"
                      &lt;&lt; "compiled with AMR support!"
                      &lt;&lt; std::endl;
          return 0;
        #else
        
</pre>
</div>
<div class = "comment">
Create a GetPot object to parse the command line
</div>

<div class ="fragment">
<pre>
          GetPot command_line (argc, argv);
          
</pre>
</div>
<div class = "comment">
Check for proper calling arguments.
</div>

<div class ="fragment">
<pre>
          if (argc &lt; 3)
            {
              if (libMesh::processor_id() == 0)
                std::cerr &lt;&lt; "Usage:\n"
                          &lt;&lt;"\t " &lt;&lt; argv[0] &lt;&lt; " -r 2"
                          &lt;&lt; std::endl;
        
</pre>
</div>
<div class = "comment">
This handy function will print the file name, line number,
and then abort.
</div>

<div class ="fragment">
<pre>
              libmesh_error();
            }
          
</pre>
</div>
<div class = "comment">
Brief message to the user regarding the program name
and command line arguments.
</div>

<div class ="fragment">
<pre>
          else 
            {
              std::cout &lt;&lt; "Running " &lt;&lt; argv[0];
              
              for (int i=1; i&lt;argc; i++)
                std::cout &lt;&lt; " " &lt;&lt; argv[i];
              
              std::cout &lt;&lt; std::endl &lt;&lt; std::endl;
            }
          
        
</pre>
</div>
<div class = "comment">
Read number of refinements 
</div>

<div class ="fragment">
<pre>
          int nr = 2;
          if ( command_line.search(1, "-r") )
            nr = command_line.next(nr);
          
</pre>
</div>
<div class = "comment">
Read FE order from command line
</div>

<div class ="fragment">
<pre>
          std::string order = "FIRST"; 
          if ( command_line.search(2, "-Order", "-o") )
            order = command_line.next(order);
        
</pre>
</div>
<div class = "comment">
Read FE Family from command line
</div>

<div class ="fragment">
<pre>
          std::string family = "LAGRANGE"; 
          if ( command_line.search(2, "-FEFamily", "-f") )
            family = command_line.next(family);
          
</pre>
</div>
<div class = "comment">
Cannot use dicontinuous basis.
</div>

<div class ="fragment">
<pre>
          if ((family == "MONOMIAL") || (family == "XYZ"))
            {
              std::cout &lt;&lt; "ex19 currently requires a C^0 (or higher) FE basis." &lt;&lt; std::endl;
              libmesh_error();
            }
        
          if ( command_line.search(1, "-pre") )
            {
        #ifdef LIBMESH_HAVE_PETSC
</pre>
</div>
<div class = "comment">
Use the jacobian for preconditioning.
</div>

<div class ="fragment">
<pre>
              PetscOptionsSetValue("-snes_mf_operator",PETSC_NULL);
        #else
              std::cerr&lt;&lt;"Must be using PetsC to use jacobian based preconditioning"&lt;&lt;std::endl;
        
</pre>
</div>
<div class = "comment">
returning zero so that "make run" won't fail if we ever enable this capability there.
</div>

<div class ="fragment">
<pre>
              return 0;
        #endif //LIBMESH_HAVE_PETSC
            }  
            
</pre>
</div>
<div class = "comment">
Skip this 2D example if libMesh was compiled as 1D-only.
</div>

<div class ="fragment">
<pre>
          libmesh_example_assert(2 &lt;= LIBMESH_DIM, "2D support");
          
</pre>
</div>
<div class = "comment">
Create a mesh from file.
</div>

<div class ="fragment">
<pre>
          Mesh mesh;    
          mesh.read ("lshaped.xda");
        
          if (order != "FIRST")
            mesh.all_second_order();
        
          MeshRefinement(mesh).uniformly_refine(nr);
        
</pre>
</div>
<div class = "comment">
Print information about the mesh to the screen.
</div>

<div class ="fragment">
<pre>
          mesh.print_info();    
          
</pre>
</div>
<div class = "comment">
Create an equation systems object.
</div>

<div class ="fragment">
<pre>
          EquationSystems equation_systems (mesh);
          _equation_system = &equation_systems;
          
</pre>
</div>
<div class = "comment">
Declare the system and its variables.
  

<br><br>Creates a system named "Laplace-Young"
</div>

<div class ="fragment">
<pre>
          NonlinearImplicitSystem& system =
            equation_systems.add_system&lt;NonlinearImplicitSystem&gt; ("Laplace-Young");
        
        
</pre>
</div>
<div class = "comment">
Here we specify the tolerance for the nonlinear solver and 
the maximum of nonlinear iterations. 
</div>

<div class ="fragment">
<pre>
          equation_systems.parameters.set&lt;Real&gt;         ("nonlinear solver tolerance")          = 1.e-12;
          equation_systems.parameters.set&lt;unsigned int&gt; ("nonlinear solver maximum iterations") = 50;
        
            
</pre>
</div>
<div class = "comment">
Adds the variable "u" to "Laplace-Young".  "u"
will be approximated using second-order approximation.
</div>

<div class ="fragment">
<pre>
          system.add_variable("u",
        		      Utility::string_to_enum&lt;Order&gt;   (order),
        		      Utility::string_to_enum&lt;FEFamily&gt;(family));
        
</pre>
</div>
<div class = "comment">
Give the system a pointer to the functions that update 
the residual and Jacobian.
</div>

<div class ="fragment">
<pre>
          system.nonlinear_solver-&gt;residual = compute_residual;
          system.nonlinear_solver-&gt;jacobian = compute_jacobian;
        
</pre>
</div>
<div class = "comment">
Initialize the data structures for the equation system.
</div>

<div class ="fragment">
<pre>
          equation_systems.init();
        
</pre>
</div>
<div class = "comment">
Prints information about the system to the screen.
</div>

<div class ="fragment">
<pre>
          equation_systems.print_info();
          
</pre>
</div>
<div class = "comment">
Solve the system "Laplace-Young", print the number of iterations
and final residual
</div>

<div class ="fragment">
<pre>
          equation_systems.get_system("Laplace-Young").solve();
        
</pre>
</div>
<div class = "comment">
Print out final convergence information.  This duplicates some
output from during the solve itself, but demonstrates another way
to get this information after the solve is complete.
</div>

<div class ="fragment">
<pre>
          std::cout &lt;&lt; "Laplace-Young system solved at nonlinear iteration "
        	    &lt;&lt; system.n_nonlinear_iterations()
        	    &lt;&lt; " , final nonlinear residual norm: "
        	    &lt;&lt; system.final_nonlinear_residual()
        	    &lt;&lt; std::endl;
        
        #ifdef LIBMESH_HAVE_EXODUS_API
</pre>
</div>
<div class = "comment">
After solving the system write the solution
</div>

<div class ="fragment">
<pre>
          ExodusII_IO (mesh).write_equation_systems ("out.exd", 
                                               equation_systems);
        #endif // #ifdef LIBMESH_HAVE_EXODUS_API
        #endif // #ifndef LIBMESH_ENABLE_AMR
        
</pre>
</div>
<div class = "comment">
All done. 
</div>

<div class ="fragment">
<pre>
          return 0; 
        }
</pre>
</div>

<a name="nocomments"></a> 
<br><br><br> <h1> The program without comments: </h1> 
<pre> 
   
  
  #include &lt;iostream&gt;
  #include &lt;algorithm&gt;
  #include &lt;cmath&gt;
  
  #include <B><FONT COLOR="#BC8F8F">&quot;libmesh.h&quot;</FONT></B>
  #include <B><FONT COLOR="#BC8F8F">&quot;mesh.h&quot;</FONT></B>
  #include <B><FONT COLOR="#BC8F8F">&quot;mesh_refinement.h&quot;</FONT></B>
  #include <B><FONT COLOR="#BC8F8F">&quot;exodusII_io.h&quot;</FONT></B>
  #include <B><FONT COLOR="#BC8F8F">&quot;equation_systems.h&quot;</FONT></B>
  #include <B><FONT COLOR="#BC8F8F">&quot;fe.h&quot;</FONT></B>
  #include <B><FONT COLOR="#BC8F8F">&quot;quadrature_gauss.h&quot;</FONT></B>
  #include <B><FONT COLOR="#BC8F8F">&quot;dof_map.h&quot;</FONT></B>
  #include <B><FONT COLOR="#BC8F8F">&quot;sparse_matrix.h&quot;</FONT></B>
  #include <B><FONT COLOR="#BC8F8F">&quot;numeric_vector.h&quot;</FONT></B>
  #include <B><FONT COLOR="#BC8F8F">&quot;dense_matrix.h&quot;</FONT></B>
  #include <B><FONT COLOR="#BC8F8F">&quot;dense_vector.h&quot;</FONT></B>
  #include <B><FONT COLOR="#BC8F8F">&quot;elem.h&quot;</FONT></B>
  #include <B><FONT COLOR="#BC8F8F">&quot;string_to_enum.h&quot;</FONT></B>
  #include <B><FONT COLOR="#BC8F8F">&quot;getpot.h&quot;</FONT></B>
  
  #include <B><FONT COLOR="#BC8F8F">&quot;nonlinear_solver.h&quot;</FONT></B>
  #include <B><FONT COLOR="#BC8F8F">&quot;nonlinear_implicit_system.h&quot;</FONT></B>
  
  #ifdef LIBMESH_HAVE_PETSC
  #include &lt;petsc.h&gt;
  #endif
  
  using namespace libMesh;
  
  EquationSystems *_equation_system = NULL;
  
  <B><FONT COLOR="#228B22">const</FONT></B> Real kappa = 1.;
  <B><FONT COLOR="#228B22">const</FONT></B> Real sigma = 0.2;
  
  
  <B><FONT COLOR="#228B22">void</FONT></B> compute_jacobian (<B><FONT COLOR="#228B22">const</FONT></B> NumericVector&lt;Number&gt;&amp; soln,
                         SparseMatrix&lt;Number&gt;&amp;  jacobian,
                         NonlinearImplicitSystem&amp; sys)
  {
    EquationSystems &amp;es = *_equation_system;
  
    <B><FONT COLOR="#228B22">const</FONT></B> MeshBase&amp; mesh = es.get_mesh();
  
    <B><FONT COLOR="#228B22">const</FONT></B> <B><FONT COLOR="#228B22">unsigned</FONT></B> <B><FONT COLOR="#228B22">int</FONT></B> dim = mesh.mesh_dimension();
  
    NonlinearImplicitSystem&amp; system = 
      es.get_system&lt;NonlinearImplicitSystem&gt;(<B><FONT COLOR="#BC8F8F">&quot;Laplace-Young&quot;</FONT></B>);
    
    <B><FONT COLOR="#228B22">const</FONT></B> DofMap&amp; dof_map = system.get_dof_map();
  
    FEType fe_type = dof_map.variable_type(0);
  
    AutoPtr&lt;FEBase&gt; fe (FEBase::build(dim, fe_type));
    
    QGauss qrule (dim, FIFTH);
  
    fe-&gt;attach_quadrature_rule (&amp;qrule);
  
    <B><FONT COLOR="#228B22">const</FONT></B> std::vector&lt;Real&gt;&amp; JxW = fe-&gt;get_JxW();
  
    <B><FONT COLOR="#228B22">const</FONT></B> std::vector&lt;std::vector&lt;Real&gt; &gt;&amp; phi = fe-&gt;get_phi();
    
    <B><FONT COLOR="#228B22">const</FONT></B> std::vector&lt;std::vector&lt;RealGradient&gt; &gt;&amp; dphi = fe-&gt;get_dphi();
  
    DenseMatrix&lt;Number&gt; Ke;
  
    <B><FONT COLOR="#5F9EA0">std</FONT></B>::vector&lt;<B><FONT COLOR="#228B22">unsigned</FONT></B> <B><FONT COLOR="#228B22">int</FONT></B>&gt; dof_indices;
  
    <B><FONT COLOR="#5F9EA0">MeshBase</FONT></B>::const_element_iterator       el     = mesh.active_local_elements_begin();
    <B><FONT COLOR="#228B22">const</FONT></B> MeshBase::const_element_iterator end_el = mesh.active_local_elements_end();
  
    <B><FONT COLOR="#A020F0">for</FONT></B> ( ; el != end_el; ++el)
      {
        <B><FONT COLOR="#228B22">const</FONT></B> Elem* elem = *el;
  
        dof_map.dof_indices (elem, dof_indices);
  
        fe-&gt;reinit (elem);
  
        Ke.resize (dof_indices.size(),
                   dof_indices.size());
             
        <B><FONT COLOR="#A020F0">for</FONT></B> (<B><FONT COLOR="#228B22">unsigned</FONT></B> <B><FONT COLOR="#228B22">int</FONT></B> qp=0; qp&lt;qrule.n_points(); qp++)
          {
            Gradient grad_u;
      
            <B><FONT COLOR="#A020F0">for</FONT></B> (<B><FONT COLOR="#228B22">unsigned</FONT></B> <B><FONT COLOR="#228B22">int</FONT></B> i=0; i&lt;phi.size(); i++)
              grad_u += dphi[i][qp]*soln(dof_indices[i]);
            
            <B><FONT COLOR="#228B22">const</FONT></B> Number K = 1./std::sqrt(1. + grad_u*grad_u);
            
            <B><FONT COLOR="#A020F0">for</FONT></B> (<B><FONT COLOR="#228B22">unsigned</FONT></B> <B><FONT COLOR="#228B22">int</FONT></B> i=0; i&lt;phi.size(); i++)
              <B><FONT COLOR="#A020F0">for</FONT></B> (<B><FONT COLOR="#228B22">unsigned</FONT></B> <B><FONT COLOR="#228B22">int</FONT></B> j=0; j&lt;phi.size(); j++)
                Ke(i,j) += JxW[qp]*(
                                    K*(dphi[i][qp]*dphi[j][qp]) +
                                    kappa*phi[i][qp]*phi[j][qp]
                                    );
          }
        
        dof_map.constrain_element_matrix (Ke, dof_indices);
        
        jacobian.add_matrix (Ke, dof_indices);
      }
  
  }
  
  
  <B><FONT COLOR="#228B22">void</FONT></B> compute_residual (<B><FONT COLOR="#228B22">const</FONT></B> NumericVector&lt;Number&gt;&amp; soln,
                         NumericVector&lt;Number&gt;&amp; residual,
                         NonlinearImplicitSystem&amp; sys)
  {
    EquationSystems &amp;es = *_equation_system;
  
    <B><FONT COLOR="#228B22">const</FONT></B> MeshBase&amp; mesh = es.get_mesh();
  
    <B><FONT COLOR="#228B22">const</FONT></B> <B><FONT COLOR="#228B22">unsigned</FONT></B> <B><FONT COLOR="#228B22">int</FONT></B> dim = mesh.mesh_dimension();
    libmesh_assert (dim == 2);
  
    NonlinearImplicitSystem&amp; system = 
      es.get_system&lt;NonlinearImplicitSystem&gt;(<B><FONT COLOR="#BC8F8F">&quot;Laplace-Young&quot;</FONT></B>);
    
    <B><FONT COLOR="#228B22">const</FONT></B> DofMap&amp; dof_map = system.get_dof_map();
  
    FEType fe_type = dof_map.variable_type(0);
  
    AutoPtr&lt;FEBase&gt; fe (FEBase::build(dim, fe_type));
    
    QGauss qrule (dim, FIFTH);
  
    fe-&gt;attach_quadrature_rule (&amp;qrule);
  
    AutoPtr&lt;FEBase&gt; fe_face (FEBase::build(dim, fe_type));
                
    QGauss qface(dim-1, FIFTH);
    
    fe_face-&gt;attach_quadrature_rule (&amp;qface);
  
    <B><FONT COLOR="#228B22">const</FONT></B> std::vector&lt;Real&gt;&amp; JxW = fe-&gt;get_JxW();
  
    <B><FONT COLOR="#228B22">const</FONT></B> std::vector&lt;std::vector&lt;Real&gt; &gt;&amp; phi = fe-&gt;get_phi();
    
    <B><FONT COLOR="#228B22">const</FONT></B> std::vector&lt;std::vector&lt;RealGradient&gt; &gt;&amp; dphi = fe-&gt;get_dphi();
  
    DenseVector&lt;Number&gt; Re;
  
    <B><FONT COLOR="#5F9EA0">std</FONT></B>::vector&lt;<B><FONT COLOR="#228B22">unsigned</FONT></B> <B><FONT COLOR="#228B22">int</FONT></B>&gt; dof_indices;
  
    residual.zero();
  
    <B><FONT COLOR="#5F9EA0">MeshBase</FONT></B>::const_element_iterator       el     = mesh.active_local_elements_begin();
    <B><FONT COLOR="#228B22">const</FONT></B> MeshBase::const_element_iterator end_el = mesh.active_local_elements_end();
  
    <B><FONT COLOR="#A020F0">for</FONT></B> ( ; el != end_el; ++el)
      {
        <B><FONT COLOR="#228B22">const</FONT></B> Elem* elem = *el;
  
        dof_map.dof_indices (elem, dof_indices);
  
        fe-&gt;reinit (elem);
  
        Re.resize (dof_indices.size());
        
  
        <B><FONT COLOR="#A020F0">for</FONT></B> (<B><FONT COLOR="#228B22">unsigned</FONT></B> <B><FONT COLOR="#228B22">int</FONT></B> qp=0; qp&lt;qrule.n_points(); qp++)
          {
            Number u = 0;
            Gradient grad_u;
            
            <B><FONT COLOR="#A020F0">for</FONT></B> (<B><FONT COLOR="#228B22">unsigned</FONT></B> <B><FONT COLOR="#228B22">int</FONT></B> j=0; j&lt;phi.size(); j++)
              {
                u      += phi[j][qp]*soln(dof_indices[j]);
                grad_u += dphi[j][qp]*soln(dof_indices[j]);
              }
            
            <B><FONT COLOR="#228B22">const</FONT></B> Number K = 1./std::sqrt(1. + grad_u*grad_u);
            
            <B><FONT COLOR="#A020F0">for</FONT></B> (<B><FONT COLOR="#228B22">unsigned</FONT></B> <B><FONT COLOR="#228B22">int</FONT></B> i=0; i&lt;phi.size(); i++)
              Re(i) += JxW[qp]*(
                                K*(dphi[i][qp]*grad_u) +
                                kappa*phi[i][qp]*u
                                );
          }
  
        
        <B><FONT COLOR="#A020F0">for</FONT></B> (<B><FONT COLOR="#228B22">unsigned</FONT></B> <B><FONT COLOR="#228B22">int</FONT></B> side=0; side&lt;elem-&gt;n_sides(); side++)
          <B><FONT COLOR="#A020F0">if</FONT></B> (elem-&gt;neighbor(side) == NULL)
            {
              <B><FONT COLOR="#228B22">const</FONT></B> std::vector&lt;std::vector&lt;Real&gt; &gt;&amp;  phi_face = fe_face-&gt;get_phi();
  
              <B><FONT COLOR="#228B22">const</FONT></B> std::vector&lt;Real&gt;&amp; JxW_face = fe_face-&gt;get_JxW();
  
              fe_face-&gt;reinit(elem, side);
  
              <B><FONT COLOR="#A020F0">for</FONT></B> (<B><FONT COLOR="#228B22">unsigned</FONT></B> <B><FONT COLOR="#228B22">int</FONT></B> qp=0; qp&lt;qface.n_points(); qp++)
                {
                  <B><FONT COLOR="#A020F0">for</FONT></B> (<B><FONT COLOR="#228B22">unsigned</FONT></B> <B><FONT COLOR="#228B22">int</FONT></B> i=0; i&lt;phi_face.size(); i++)
                    Re(i) -= JxW_face[qp]*sigma*phi_face[i][qp];
                } 
            }
        
        dof_map.constrain_element_vector (Re, dof_indices);
        residual.add_vector (Re, dof_indices);
      }
  
  }
  
  
  
  <B><FONT COLOR="#228B22">int</FONT></B> main (<B><FONT COLOR="#228B22">int</FONT></B> argc, <B><FONT COLOR="#228B22">char</FONT></B>** argv)
  {
    LibMeshInit init (argc, argv);
  
  #<B><FONT COLOR="#A020F0">if</FONT></B> !defined(LIBMESH_HAVE_PETSC) &amp;&amp; !defined(LIBMESH_HAVE_TRILINOS)
    <B><FONT COLOR="#A020F0">if</FONT></B> (libMesh::processor_id() == 0)
      <B><FONT COLOR="#5F9EA0">std</FONT></B>::cerr &lt;&lt; <B><FONT COLOR="#BC8F8F">&quot;ERROR: This example requires libMesh to be\n&quot;</FONT></B>
                &lt;&lt; <B><FONT COLOR="#BC8F8F">&quot;compiled with nonlinear solver support from\n&quot;</FONT></B>
                &lt;&lt; <B><FONT COLOR="#BC8F8F">&quot;PETSc or Trilinos!&quot;</FONT></B>
                &lt;&lt; std::endl;
    <B><FONT COLOR="#A020F0">return</FONT></B> 0;
  #endif
  
  #ifndef LIBMESH_ENABLE_AMR
    <B><FONT COLOR="#A020F0">if</FONT></B> (libMesh::processor_id() == 0)
      <B><FONT COLOR="#5F9EA0">std</FONT></B>::cerr &lt;&lt; <B><FONT COLOR="#BC8F8F">&quot;ERROR: This example requires libMesh to be\n&quot;</FONT></B>
                &lt;&lt; <B><FONT COLOR="#BC8F8F">&quot;compiled with AMR support!&quot;</FONT></B>
                &lt;&lt; std::endl;
    <B><FONT COLOR="#A020F0">return</FONT></B> 0;
  #<B><FONT COLOR="#A020F0">else</FONT></B>
  
    GetPot command_line (argc, argv);
    
    <B><FONT COLOR="#A020F0">if</FONT></B> (argc &lt; 3)
      {
        <B><FONT COLOR="#A020F0">if</FONT></B> (libMesh::processor_id() == 0)
          <B><FONT COLOR="#5F9EA0">std</FONT></B>::cerr &lt;&lt; <B><FONT COLOR="#BC8F8F">&quot;Usage:\n&quot;</FONT></B>
                    &lt;&lt;<B><FONT COLOR="#BC8F8F">&quot;\t &quot;</FONT></B> &lt;&lt; argv[0] &lt;&lt; <B><FONT COLOR="#BC8F8F">&quot; -r 2&quot;</FONT></B>
                    &lt;&lt; std::endl;
  
        libmesh_error();
      }
    
    <B><FONT COLOR="#A020F0">else</FONT></B> 
      {
        <B><FONT COLOR="#5F9EA0">std</FONT></B>::cout &lt;&lt; <B><FONT COLOR="#BC8F8F">&quot;Running &quot;</FONT></B> &lt;&lt; argv[0];
        
        <B><FONT COLOR="#A020F0">for</FONT></B> (<B><FONT COLOR="#228B22">int</FONT></B> i=1; i&lt;argc; i++)
          <B><FONT COLOR="#5F9EA0">std</FONT></B>::cout &lt;&lt; <B><FONT COLOR="#BC8F8F">&quot; &quot;</FONT></B> &lt;&lt; argv[i];
        
        <B><FONT COLOR="#5F9EA0">std</FONT></B>::cout &lt;&lt; std::endl &lt;&lt; std::endl;
      }
    
  
    <B><FONT COLOR="#228B22">int</FONT></B> nr = 2;
    <B><FONT COLOR="#A020F0">if</FONT></B> ( command_line.search(1, <B><FONT COLOR="#BC8F8F">&quot;-r&quot;</FONT></B>) )
      nr = command_line.next(nr);
    
    <B><FONT COLOR="#5F9EA0">std</FONT></B>::string order = <B><FONT COLOR="#BC8F8F">&quot;FIRST&quot;</FONT></B>; 
    <B><FONT COLOR="#A020F0">if</FONT></B> ( command_line.search(2, <B><FONT COLOR="#BC8F8F">&quot;-Order&quot;</FONT></B>, <B><FONT COLOR="#BC8F8F">&quot;-o&quot;</FONT></B>) )
      order = command_line.next(order);
  
    <B><FONT COLOR="#5F9EA0">std</FONT></B>::string family = <B><FONT COLOR="#BC8F8F">&quot;LAGRANGE&quot;</FONT></B>; 
    <B><FONT COLOR="#A020F0">if</FONT></B> ( command_line.search(2, <B><FONT COLOR="#BC8F8F">&quot;-FEFamily&quot;</FONT></B>, <B><FONT COLOR="#BC8F8F">&quot;-f&quot;</FONT></B>) )
      family = command_line.next(family);
    
    <B><FONT COLOR="#A020F0">if</FONT></B> ((family == <B><FONT COLOR="#BC8F8F">&quot;MONOMIAL&quot;</FONT></B>) || (family == <B><FONT COLOR="#BC8F8F">&quot;XYZ&quot;</FONT></B>))
      {
        <B><FONT COLOR="#5F9EA0">std</FONT></B>::cout &lt;&lt; <B><FONT COLOR="#BC8F8F">&quot;ex19 currently requires a C^0 (or higher) FE basis.&quot;</FONT></B> &lt;&lt; std::endl;
        libmesh_error();
      }
  
    <B><FONT COLOR="#A020F0">if</FONT></B> ( command_line.search(1, <B><FONT COLOR="#BC8F8F">&quot;-pre&quot;</FONT></B>) )
      {
  #ifdef LIBMESH_HAVE_PETSC
        PetscOptionsSetValue(<B><FONT COLOR="#BC8F8F">&quot;-snes_mf_operator&quot;</FONT></B>,PETSC_NULL);
  #<B><FONT COLOR="#A020F0">else</FONT></B>
        <B><FONT COLOR="#5F9EA0">std</FONT></B>::cerr&lt;&lt;<B><FONT COLOR="#BC8F8F">&quot;Must be using PetsC to use jacobian based preconditioning&quot;</FONT></B>&lt;&lt;std::endl;
  
        <B><FONT COLOR="#A020F0">return</FONT></B> 0;
  #endif <I><FONT COLOR="#B22222">//LIBMESH_HAVE_PETSC
</FONT></I>      }  
      
    libmesh_example_assert(2 &lt;= LIBMESH_DIM, <B><FONT COLOR="#BC8F8F">&quot;2D support&quot;</FONT></B>);
    
    Mesh mesh;    
    mesh.read (<B><FONT COLOR="#BC8F8F">&quot;lshaped.xda&quot;</FONT></B>);
  
    <B><FONT COLOR="#A020F0">if</FONT></B> (order != <B><FONT COLOR="#BC8F8F">&quot;FIRST&quot;</FONT></B>)
      mesh.all_second_order();
  
    MeshRefinement(mesh).uniformly_refine(nr);
  
    mesh.print_info();    
    
    EquationSystems equation_systems (mesh);
    _equation_system = &amp;equation_systems;
    
    
    NonlinearImplicitSystem&amp; system =
      equation_systems.add_system&lt;NonlinearImplicitSystem&gt; (<B><FONT COLOR="#BC8F8F">&quot;Laplace-Young&quot;</FONT></B>);
  
  
    equation_systems.parameters.set&lt;Real&gt;         (<B><FONT COLOR="#BC8F8F">&quot;nonlinear solver tolerance&quot;</FONT></B>)          = 1.e-12;
    equation_systems.parameters.set&lt;<B><FONT COLOR="#228B22">unsigned</FONT></B> <B><FONT COLOR="#228B22">int</FONT></B>&gt; (<B><FONT COLOR="#BC8F8F">&quot;nonlinear solver maximum iterations&quot;</FONT></B>) = 50;
  
      
    system.add_variable(<B><FONT COLOR="#BC8F8F">&quot;u&quot;</FONT></B>,
  		      <B><FONT COLOR="#5F9EA0">Utility</FONT></B>::string_to_enum&lt;Order&gt;   (order),
  		      <B><FONT COLOR="#5F9EA0">Utility</FONT></B>::string_to_enum&lt;FEFamily&gt;(family));
  
    system.nonlinear_solver-&gt;residual = compute_residual;
    system.nonlinear_solver-&gt;jacobian = compute_jacobian;
  
    equation_systems.init();
  
    equation_systems.print_info();
    
    equation_systems.get_system(<B><FONT COLOR="#BC8F8F">&quot;Laplace-Young&quot;</FONT></B>).solve();
  
    <B><FONT COLOR="#5F9EA0">std</FONT></B>::cout &lt;&lt; <B><FONT COLOR="#BC8F8F">&quot;Laplace-Young system solved at nonlinear iteration &quot;</FONT></B>
  	    &lt;&lt; system.n_nonlinear_iterations()
  	    &lt;&lt; <B><FONT COLOR="#BC8F8F">&quot; , final nonlinear residual norm: &quot;</FONT></B>
  	    &lt;&lt; system.final_nonlinear_residual()
  	    &lt;&lt; std::endl;
  
  #ifdef LIBMESH_HAVE_EXODUS_API
    ExodusII_IO (mesh).write_equation_systems (<B><FONT COLOR="#BC8F8F">&quot;out.exd&quot;</FONT></B>, 
                                         equation_systems);
  #endif <I><FONT COLOR="#B22222">// #ifdef LIBMESH_HAVE_EXODUS_API
</FONT></I>  #endif <I><FONT COLOR="#B22222">// #ifndef LIBMESH_ENABLE_AMR
</FONT></I>  
    <B><FONT COLOR="#A020F0">return</FONT></B> 0; 
  }
</pre> 
<a name="output"></a> 
<br><br><br> <h1> The console output of the program: </h1> 
<pre>
Compiling C++ (in optimized mode) ex19.C...
Linking ex19-opt...
***************************************************************
* Running Example  mpirun -np 2 ./ex19-opt -pc_type bjacobi -sub_pc_type ilu -sub_pc_factor_levels 4 -sub_pc_factor_zeropivot 0 -ksp_right_pc -log_summary
***************************************************************
 
Running ./ex19-opt -r 3 -o FIRST -pc_type bjacobi -sub_pc_type ilu -sub_pc_factor_levels 4 -sub_pc_factor_zeropivot 0 -ksp_right_pc -log_summary

 Mesh Information:
  mesh_dimension()=2
  spatial_dimension()=3
  n_nodes()=225
    n_local_nodes()=121
  n_elem()=255
    n_local_elem()=131
    n_active_elem()=192
  n_subdomains()=1
  n_processors()=2
  processor_id()=0

 EquationSystems
  n_systems()=1
   System "Laplace-Young"
    Type "NonlinearImplicit"
    Variables="u" 
    Finite Element Types="LAGRANGE" 
    Approximation Orders="FIRST" 
    n_dofs()=225
    n_local_dofs()=121
    n_constrained_dofs()=0
    n_vectors()=1

  NL step  0, |residual|_2 = 2.000000e-01
  NL step  1, |residual|_2 = 4.433141e-03
  NL step  2, |residual|_2 = 2.163897e-04
  NL step  3, |residual|_2 = 1.157741e-05
  NL step  4, |residual|_2 = 6.567644e-07
  NL step  5, |residual|_2 = 3.849565e-08
  NL step  6, |residual|_2 = 2.293625e-09
Laplace-Young system solved at nonlinear iteration 6 , final nonlinear residual norm: 2.293625e-09
************************************************************************************************************************
***             WIDEN YOUR WINDOW TO 120 CHARACTERS.  Use 'enscript -r -fCourier9' to print this document            ***
************************************************************************************************************************

---------------------------------------------- PETSc Performance Summary: ----------------------------------------------

./ex19-opt on a gcc-4.5-l named daedalus with 2 processors, by roystgnr Tue Feb 22 12:21:52 2011
Using Petsc Release Version 3.1.0, Patch 5, Mon Sep 27 11:51:54 CDT 2010

                         Max       Max/Min        Avg      Total 
Time (sec):           2.006e-02      1.03455   1.973e-02
Objects:              8.400e+01      1.00000   8.400e+01
Flops:                9.677e+05      1.18806   8.911e+05  1.782e+06
Flops/sec:            4.824e+07      1.14838   4.512e+07  9.024e+07
MPI Messages:         1.730e+02      1.00000   1.730e+02  3.460e+02
MPI Message Lengths:  3.414e+04      1.05907   1.918e+02  6.637e+04
MPI Reductions:       3.970e+02      1.00000

Flop counting convention: 1 flop = 1 real number operation of type (multiply/divide/add/subtract)
                            e.g., VecAXPY() for real vectors of length N --> 2N flops
                            and VecAXPY() for complex vectors of length N --> 8N flops

Summary of Stages:   ----- Time ------  ----- Flops -----  --- Messages ---  -- Message Lengths --  -- Reductions --
                        Avg     %Total     Avg     %Total   counts   %Total     Avg         %Total   counts   %Total 
 0:      Main Stage: 1.9701e-02  99.9%  1.7822e+06 100.0%  3.460e+02 100.0%  1.918e+02      100.0%  3.810e+02  96.0% 

------------------------------------------------------------------------------------------------------------------------
See the 'Profiling' chapter of the users' manual for details on interpreting output.
Phase summary info:
   Count: number of times phase was executed
   Time and Flops: Max - maximum over all processors
                   Ratio - ratio of maximum to minimum over all processors
   Mess: number of messages sent
   Avg. len: average message length
   Reduct: number of global reductions
   Global: entire computation
   Stage: stages of a computation. Set stages with PetscLogStagePush() and PetscLogStagePop().
      %T - percent time in this phase         %F - percent flops in this phase
      %M - percent messages in this phase     %L - percent message lengths in this phase
      %R - percent reductions in this phase
   Total Mflop/s: 10e-6 * (sum of flops over all processors)/(max time over all processors)
------------------------------------------------------------------------------------------------------------------------
Event                Count      Time (sec)     Flops                             --- Global ---  --- Stage ---   Total
                   Max Ratio  Max     Ratio   Max  Ratio  Mess   Avg len Reduct  %T %F %M %L %R  %T %F %M %L %R Mflop/s
------------------------------------------------------------------------------------------------------------------------

--- Event Stage 0: Main Stage

VecDot                 6 1.0 3.9339e-05 1.2 1.45e+03 1.2 0.0e+00 0.0e+00 6.0e+00  0  0  0  0  2   0  0  0  0  2    68
VecMDot               77 1.0 3.7003e-04 1.7 1.29e+05 1.2 0.0e+00 0.0e+00 7.7e+01  1 13  0  0 19   1 13  0  0 20   647
VecNorm               95 1.0 2.8348e-04 1.3 2.30e+04 1.2 0.0e+00 0.0e+00 9.5e+01  1  2  0  0 24   1  2  0  0 25   151
VecScale              83 1.0 4.5300e-05 1.1 1.00e+04 1.2 0.0e+00 0.0e+00 0.0e+00  0  1  0  0  0   0  1  0  0  0   412
VecCopy               36 1.0 9.7752e-06 1.2 0.00e+00 0.0 0.0e+00 0.0e+00 0.0e+00  0  0  0  0  0   0  0  0  0  0     0
VecSet               112 1.0 1.8835e-05 1.5 0.00e+00 0.0 0.0e+00 0.0e+00 0.0e+00  0  0  0  0  0   0  0  0  0  0     0
VecAXPY                6 1.0 2.4080e-05 1.1 1.45e+03 1.2 0.0e+00 0.0e+00 0.0e+00  0  0  0  0  0   0  0  0  0  0   112
VecWAXPY               6 1.0 7.1526e-06 1.0 7.26e+02 1.2 0.0e+00 0.0e+00 0.0e+00  0  0  0  0  0   0  0  0  0  0   189
VecMAXPY              83 1.0 5.8174e-05 1.2 1.48e+05 1.2 0.0e+00 0.0e+00 0.0e+00  0 15  0  0  0   0 15  0  0  0  4726
VecAssemblyBegin      34 1.0 5.3287e-04 2.2 0.00e+00 0.0 1.4e+01 1.6e+02 1.0e+02  2  0  4  3 26   2  0  4  3 27     0
VecAssemblyEnd        34 1.0 2.9325e-05 1.1 0.00e+00 0.0 0.0e+00 0.0e+00 0.0e+00  0  0  0  0  0   0  0  0  0  0     0
VecScatterBegin      111 1.0 1.3471e-04 1.1 0.00e+00 0.0 1.9e+02 1.2e+02 0.0e+00  1  0 56 35  0   1  0 56 35  0     0
VecScatterEnd        111 1.0 3.0851e-04 3.3 0.00e+00 0.0 0.0e+00 0.0e+00 0.0e+00  1  0  0  0  0   1  0  0  0  0     0
VecReduceArith         2 1.0 3.2840e-03 1.0 4.82e+02 1.2 0.0e+00 0.0e+00 0.0e+00 17  0  0  0  0  17  0  0  0  0     0
VecReduceComm          1 1.0 7.1526e-06 1.2 0.00e+00 0.0 0.0e+00 0.0e+00 1.0e+00  0  0  0  0  0   0  0  0  0  0     0
VecNormalize          83 1.0 2.4056e-04 1.0 2.87e+04 1.2 0.0e+00 0.0e+00 7.7e+01  1  3  0  0 19   1  3  0  0 20   222
MatMult               83 1.0 5.4336e-04 1.6 1.52e+05 1.2 1.7e+02 1.1e+02 0.0e+00  2 16 48 28  0   2 16 48 28  0   523
MatSolve              83 1.0 5.1188e-04 1.9 3.67e+05 1.2 0.0e+00 0.0e+00 0.0e+00  2 38  0  0  0   2 38  0  0  0  1317
MatLUFactorNum         6 1.0 2.7323e-04 1.5 1.35e+05 1.3 0.0e+00 0.0e+00 0.0e+00  1 13  0  0  0   1 13  0  0  0   876
MatILUFactorSym        1 1.0 1.5998e-04 1.2 0.00e+00 0.0 0.0e+00 0.0e+00 1.0e+00  1  0  0  0  0   1  0  0  0  0     0
MatAssemblyBegin      12 1.0 2.2483e-04 1.2 0.00e+00 0.0 1.8e+01 5.6e+02 2.4e+01  1  0  5 15  6   1  0  5 15  6     0
MatAssemblyEnd        12 1.0 2.9731e-04 1.0 0.00e+00 0.0 4.0e+00 3.0e+01 1.8e+01  1  0  1  0  5   1  0  1  0  5     0
MatGetRowIJ            1 1.0 0.0000e+00 0.0 0.00e+00 0.0 0.0e+00 0.0e+00 0.0e+00  0  0  0  0  0   0  0  0  0  0     0
MatGetOrdering         1 1.0 2.7895e-05 1.1 0.00e+00 0.0 0.0e+00 0.0e+00 2.0e+00  0  0  0  0  1   0  0  0  0  1     0
MatZeroEntries         8 1.0 1.2875e-05 1.1 0.00e+00 0.0 0.0e+00 0.0e+00 0.0e+00  0  0  0  0  0   0  0  0  0  0     0
SNESSolve              1 1.0 1.2887e-02 1.0 9.68e+05 1.2 3.3e+02 1.9e+02 3.7e+02 65100 96 96 93  65100 96 96 97   138
SNESLineSearch         6 1.0 3.3305e-03 1.0 1.68e+04 1.2 8.4e+01 2.2e+02 9.6e+01 17  2 24 28 24  17  2 24 28 25     9
SNESFunctionEval       7 1.0 3.9608e-03 1.0 0.00e+00 0.0 8.4e+01 2.4e+02 8.4e+01 20  0 24 30 21  20  0 24 30 22     0
SNESJacobianEval       6 1.0 2.5954e-03 1.0 0.00e+00 0.0 8.2e+01 3.1e+02 9.6e+01 13  0 24 38 24  13  0 24 38 25     0
KSPGMRESOrthog        77 1.0 4.4370e-04 1.5 2.58e+05 1.2 0.0e+00 0.0e+00 7.7e+01  2 27  0  0 19   2 27  0  0 20  1081
KSPSetup              12 1.0 2.5034e-05 1.0 0.00e+00 0.0 0.0e+00 0.0e+00 0.0e+00  0  0  0  0  0   0  0  0  0  0     0
KSPSolve               6 1.0 2.6822e-03 1.0 9.49e+05 1.2 1.5e+02 1.1e+02 1.6e+02 14 98 45 26 40  14 98 45 26 41   651
PCSetUp               12 1.0 6.6876e-04 1.2 1.35e+05 1.3 0.0e+00 0.0e+00 3.0e+00  3 13  0  0  1   3 13  0  0  1   358
PCSetUpOnBlocks        6 1.0 5.2285e-04 1.3 1.35e+05 1.3 0.0e+00 0.0e+00 3.0e+00  2 13  0  0  1   2 13  0  0  1   458
PCApply               83 1.0 8.8954e-04 1.4 3.67e+05 1.2 0.0e+00 0.0e+00 0.0e+00  4 38  0  0  0   4 38  0  0  0   758
------------------------------------------------------------------------------------------------------------------------

Memory usage is given in bytes:

Object Type          Creations   Destructions     Memory  Descendants' Mem.
Reports information only for process 0.

--- Event Stage 0: Main Stage

                 Vec    37             37        80672     0
         Vec Scatter    16             16        13888     0
           Index Set    21             21        12476     0
   IS L to G Mapping     1              1          944     0
              Matrix     4              4        51660     0
                SNES     1              1         1032     0
       Krylov Solver     2              2        18880     0
      Preconditioner     2              2         1408     0
========================================================================================================================
Average time to get PetscTime(): 0
Average time for MPI_Barrier(): 1.19209e-06
Average time for zero size MPI_Send(): 4.52995e-06
#PETSc Option Table entries:
-ksp_right_pc
-log_summary
-o FIRST
-pc_type bjacobi
-r 3
-sub_pc_factor_levels 4
-sub_pc_factor_zeropivot 0
-sub_pc_type ilu
#End of PETSc Option Table entries
Compiled without FORTRAN kernels
Compiled with full precision matrices (default)
sizeof(short) 2 sizeof(int) 4 sizeof(long) 8 sizeof(void*) 8 sizeof(PetscScalar) 8
Configure run at: Fri Oct 15 13:01:23 2010
Configure options: --with-debugging=false --COPTFLAGS=-O3 --CXXOPTFLAGS=-O3 --FOPTFLAGS=-O3 --with-clanguage=C++ --with-shared=1 --with-mpi-dir=/org/centers/pecos/LIBRARIES/MPICH2/mpich2-1.2.1-gcc-4.5-lucid --with-mumps=true --download-mumps=ifneeded --with-parmetis=true --download-parmetis=ifneeded --with-superlu=true --download-superlu=ifneeded --with-superludir=true --download-superlu_dist=ifneeded --with-blacs=true --download-blacs=ifneeded --with-scalapack=true --download-scalapack=ifneeded --with-hypre=true --download-hypre=ifneeded --with-blas-lib="[/org/centers/pecos/LIBRARIES/MKL/mkl-10.0.3.020-gcc-4.5-lucid/lib/em64t/libmkl_intel_lp64.so,/org/centers/pecos/LIBRARIES/MKL/mkl-10.0.3.020-gcc-4.5-lucid/lib/em64t/libmkl_sequential.so,/org/centers/pecos/LIBRARIES/MKL/mkl-10.0.3.020-gcc-4.5-lucid/lib/em64t/libmkl_core.so]" --with-lapack-lib=/org/centers/pecos/LIBRARIES/MKL/mkl-10.0.3.020-gcc-4.5-lucid/lib/em64t/libmkl_solver_lp64_sequential.a
-----------------------------------------
Libraries compiled on Fri Oct 15 13:01:23 CDT 2010 on atreides 
Machine characteristics: Linux atreides 2.6.32-25-generic #44-Ubuntu SMP Fri Sep 17 20:05:27 UTC 2010 x86_64 GNU/Linux 
Using PETSc directory: /org/centers/pecos/LIBRARIES/PETSC3/petsc-3.1-p5
Using PETSc arch: gcc-4.5-lucid-mpich2-1.2.1-cxx-opt
-----------------------------------------
Using C compiler: /org/centers/pecos/LIBRARIES/MPICH2/mpich2-1.2.1-gcc-4.5-lucid/bin/mpicxx -Wall -Wwrite-strings -Wno-strict-aliasing -O3   -fPIC   
Using Fortran compiler: /org/centers/pecos/LIBRARIES/MPICH2/mpich2-1.2.1-gcc-4.5-lucid/bin/mpif90 -fPIC -Wall -Wno-unused-variable -O3    
-----------------------------------------
Using include paths: -I/org/centers/pecos/LIBRARIES/PETSC3/petsc-3.1-p5/gcc-4.5-lucid-mpich2-1.2.1-cxx-opt/include -I/org/centers/pecos/LIBRARIES/PETSC3/petsc-3.1-p5/include -I/org/centers/pecos/LIBRARIES/PETSC3/petsc-3.1-p5/gcc-4.5-lucid-mpich2-1.2.1-cxx-opt/include -I/org/centers/pecos/LIBRARIES/MPICH2/mpich2-1.2.1-gcc-4.5-lucid/include  
------------------------------------------
Using C linker: /org/centers/pecos/LIBRARIES/MPICH2/mpich2-1.2.1-gcc-4.5-lucid/bin/mpicxx -Wall -Wwrite-strings -Wno-strict-aliasing -O3 
Using Fortran linker: /org/centers/pecos/LIBRARIES/MPICH2/mpich2-1.2.1-gcc-4.5-lucid/bin/mpif90 -fPIC -Wall -Wno-unused-variable -O3  
Using libraries: -Wl,-rpath,/org/centers/pecos/LIBRARIES/PETSC3/petsc-3.1-p5/gcc-4.5-lucid-mpich2-1.2.1-cxx-opt/lib -L/org/centers/pecos/LIBRARIES/PETSC3/petsc-3.1-p5/gcc-4.5-lucid-mpich2-1.2.1-cxx-opt/lib -lpetsc       -lX11 -Wl,-rpath,/org/centers/pecos/LIBRARIES/PETSC3/petsc-3.1-p5/gcc-4.5-lucid-mpich2-1.2.1-cxx-opt/lib -L/org/centers/pecos/LIBRARIES/PETSC3/petsc-3.1-p5/gcc-4.5-lucid-mpich2-1.2.1-cxx-opt/lib -lHYPRE -lsuperlu_dist_2.4 -lcmumps -ldmumps -lsmumps -lzmumps -lmumps_common -lpord -lparmetis -lmetis -lscalapack -lblacs -lsuperlu_4.0 -Wl,-rpath,/org/centers/pecos/LIBRARIES/MKL/mkl-10.0.3.020-gcc-4.5-lucid/lib/em64t -L/org/centers/pecos/LIBRARIES/MKL/mkl-10.0.3.020-gcc-4.5-lucid/lib/em64t -lmkl_solver_lp64_sequential -lmkl_intel_lp64 -lmkl_sequential -lmkl_core -lm -Wl,-rpath,/org/centers/pecos/LIBRARIES/MPICH2/mpich2-1.2.1-gcc-4.5-lucid/lib -L/org/centers/pecos/LIBRARIES/MPICH2/mpich2-1.2.1-gcc-4.5-lucid/lib -Wl,-rpath,/org/centers/pecos/LIBRARIES/GCC/gcc-4.5.1-lucid/lib/gcc/x86_64-unknown-linux-gnu/4.5.1 -L/org/centers/pecos/LIBRARIES/GCC/gcc-4.5.1-lucid/lib/gcc/x86_64-unknown-linux-gnu/4.5.1 -Wl,-rpath,/org/centers/pecos/LIBRARIES/GCC/gcc-4.5.1-lucid/lib64 -L/org/centers/pecos/LIBRARIES/GCC/gcc-4.5.1-lucid/lib64 -Wl,-rpath,/org/centers/pecos/LIBRARIES/GCC/gcc-4.5.1-lucid/lib -L/org/centers/pecos/LIBRARIES/GCC/gcc-4.5.1-lucid/lib -ldl -lmpich -lopa -lpthread -lrt -lgcc_s -lmpichf90 -lgfortran -lm -lm -lmpichcxx -lstdc++ -ldl -lmpich -lopa -lpthread -lrt -lgcc_s -ldl  
------------------------------------------
 
***************************************************************
* Done Running Example  mpirun -np 2 ./ex19-opt -pc_type bjacobi -sub_pc_type ilu -sub_pc_factor_levels 4 -sub_pc_factor_zeropivot 0 -ksp_right_pc -log_summary
***************************************************************
</pre>
</div>
<?php make_footer() ?>
</body>
</html>
<?php if (0) { ?>
\#Local Variables:
\#mode: html
\#End:
<?php } ?>
